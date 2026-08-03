<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Session;

class AttendanceController extends Controller
{
    private const PUNCHLESS_SPECIAL_RECORD_TYPES = [
        'Congé annuel',
        'Congé de maladie',
        'Ordre de mission',
    ];

    private function clearPunchesForSpecialRecord(array $record): array
    {
        if (!in_array(trim((string) ($record['Exception'] ?? '')), self::PUNCHLESS_SPECIAL_RECORD_TYPES, true)) {
            return $record;
        }

        foreach (['Premier Entree', 'Sortie Final', 'e1', 's1', 'e2', 's2', 'e3', 's3', 'e4', 's4'] as $field) {
            $record[$field] = null;
        }
        foreach ([
            'Retard 1 ', 'Sortie en avance 1 ', 'Retard 2 ', 'Sortie en avance 2 ',
            'la somme Sortie en avance', 'la somme Retard',
            'la somme du Retards General', 'Heure Supp',
        ] as $field) {
            $record[$field] = '00:00:00';
        }

        return $record;
    }

    private function isSimpleAttendanceCategory(?string $category): bool
    {
        return in_array($category, ['complex', 'vacataire_emadu'], true);
    }

    private function detailedExportColumns(?string $category): array
    {
        $baseColumns = [
            ['header' => 'Date', 'key' => 'Date'],
            ['header' => 'Nom', 'key' => 'Nom'],
            ['header' => 'ID utilisateur', 'key' => 'ID utilisateur'],
            ['header' => 'Departement', 'key' => 'Département'],
            ['header' => 'Status', 'key' => 'Status'],
            ['header' => 'Exception', 'key' => 'Exception'],
            ['header' => 'Premier Entree', 'key' => 'Premier Entree'],
            ['header' => 'Sortie Final', 'key' => 'Sortie Final'],
        ];

        if ($this->isSimpleAttendanceCategory($category)) {
            return array_merge($baseColumns, [
                ['header' => 'SUM GEN', 'key' => 'la somme du Retards General'],
            ]);
        }

        return array_merge($baseColumns, [
            ['header' => 'R1', 'key' => 'Retard 1 '],
            ['header' => 'SA1', 'key' => 'Sortie en avance 1 '],
            ['header' => 'R2', 'key' => 'Retard 2 '],
            ['header' => 'SA2', 'key' => 'Sortie en avance 2 '],
            ['header' => 'SUM SA', 'key' => 'la somme Sortie en avance'],
            ['header' => 'SUM R', 'key' => 'la somme Retard'],
            ['header' => 'SUM GEN', 'key' => 'la somme du Retards General'],
            ['header' => 'Heure Supp', 'key' => 'Heure Supp'],
        ]);
    }
    
    public function getAttendanceData(Request $request)
    {
        // Check if user is logged in
        $user = Session::get('user');
        if (!$user) {
            return redirect()->route('login')->with('error', 'Veuillez vous connecter');
        }

        // droit 0 = all categories, 1 = personnel only, 2 = doctorants only, 3 = complex only
        $userDroit = $user->droit ?? 1;

         $validated = $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'category' => 'nullable|string',
        'group_id' => 'nullable|string',
        'person_id' => 'nullable|string',
    ]);

    // Use the submitted values and add time if needed
    $startDate = $validated['start_date'] . ' 00:00:00';
    $endDate = $validated['end_date'] . ' 23:59:59';
    $category = $request->category;
    $groupId = $request->group_id;
    $personId = $request->person_id;

    // Enforce category based on droit
    if ($userDroit == 0) {
        // All categories allowed — default to personnel if none/invalid submitted
        if (!in_array($category, ['personnel', 'doctorants', 'complex', 'vacataire_emadu'])) {
            $category = 'personnel';
        }
    } elseif ($userDroit == 1) {
        $category = 'personnel';
    } elseif ($userDroit == 2) {
        $category = 'doctorants';
    } elseif ($userDroit == 3) {
        $category = 'complex';
    }

        // -----------------------------------------------------------------------
        // Cache check: if the requested range is within the last 2 months and
        // the nightly pre-compute job has already run, serve instantly from cache.
        // -----------------------------------------------------------------------
        $cachedResult = $this->getCachedAttendanceResult(
            $category,
            $validated['start_date'],
            $validated['end_date'],
            $groupId,
            $personId
        );
        if ($cachedResult !== null) {
            $allColumns = [
                'date', 'nom', 'id_utilisateur', 'departement', 'status', 'exception',
                'premier_entree', 'sortie_final', 'r1', 'sa1', 'r2', 'sa2',
                'sum_sa', 'sum_r', 'sum_gen', 'hour_supp',
            ];

            if (in_array($category, ['complex', 'vacataire_emadu'])) {
                $allColumns = array_values(array_diff($allColumns, ['r1', 'sa1', 'r2', 'sa2', 'sum_sa', 'sum_r', 'hour_supp']));
            }

            $selectedColumns = $request->input('columns', session('selected_columns', $allColumns));

            if (in_array($category, ['complex', 'vacataire_emadu'])) {
                $selectedColumns = array_values(array_diff($selectedColumns, ['r1', 'sa1', 'r2', 'sa2', 'sum_sa', 'sum_r', 'hour_supp']));
            }

            session(['selected_columns' => $selectedColumns]);

            return view('welcome', [
                'attendanceData'  => $cachedResult,
                'selectedColumns' => $selectedColumns,
                'start_date'      => $request->input('start_date'),
                'end_date'        => $request->input('end_date'),
                'category'        => $category,
                'group_id'        => $groupId,
                'person_id'       => $personId,
                'userDroit'       => $userDroit,
                'persons'         => $this->loadPersonsByDroit($userDroit),
            ]);
        }
        // -----------------------------------------------------------------------

        // Set collation before running the main query
        DB::statement("SET collation_connection = 'utf8_general_ci'");
        if ($category === 'personnel'){
        // Build dynamic WHERE conditions
        $whereConditions = "";
        $params = [$startDate, $endDate];
        
        if ($groupId && $groupId !== '' && $groupId !== 'all') {
            $whereConditions .= " AND g.usrgruid = ?";
            $params[] = $groupId;
        }
        
        if ($personId && $personId !== '' && $personId !== 'all') {
            $whereConditions .= " AND u.usrid = ?";
            $params[] = $personId;
        }

        // Build parameters for each part of the UNION query
        $modifiedPunchParams = [$startDate, $endDate];
        $leaveParams1 = [$startDate, $endDate];
        $leaveParams2 = [$startDate, $endDate];
        
        if ($groupId && $groupId !== '' && $groupId !== 'all') {
            $modifiedPunchParams[] = $groupId;
            $leaveParams1[] = $groupId;
            $leaveParams2[] = $groupId;
        }
        
        if ($personId && $personId !== '' && $personId !== 'all') {
            $modifiedPunchParams[] = $personId;
            $leaveParams1[] = $personId;
            $leaveParams2[] = $personId;
        }
        
        // Build all parameters array for the UNION query
        $allParams = array_merge(
            $params, // First query params
            $modifiedPunchParams, // Modified punch log params
            $leaveParams1, // First leave query params
            $leaveParams2 // Second leave query params
        );

        $results = DB::connection('biostar_tna')->select(" 
                  SELECT
                DATE_ADD(l.devdt, INTERVAL 1 HOUR) AS srvdt,
                CAST(l.devid AS CHAR) AS devuid,
                CAST(u.usrid AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Original' AS record_type
            FROM punchlog l
            JOIN biostar2_ac.t_usr u 
            ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g 
            ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                -- Level 1
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002

                UNION

                -- Level 2
                SELECT g2.usrgruid
                FROM biostar2_ac.t_usrgr g1
                JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                WHERE g1.parentusrgruid = 1002
            )
            AND l.devid IN (
                546271448, 546271450, 546271459, 546271456,
                546271451, 546271446, 546271441, 546271465,
                546271439, 546271455, 546271457, 546271449
            )
            AND l.bsevtc = 4102
            AND l.devdt BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(device_datetime, INTERVAL 1 HOUR) AS srvdt,
                CAST(type AS CHAR) AS devuid,
                CAST(USER_ID AS CHAR) AS usrid,
                CAST(user_name AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Modified' AS record_type
            FROM modifiedpunchlog
            JOIN biostar2_ac.t_usr u 
            ON USER_ID COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g 
            ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                UNION
                SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                WHERE g1.parentusrgruid = 1002
            )
            AND device_datetime BETWEEN ? AND ?
            {$whereConditions}
            
            UNION ALL
            
            SELECT
                DATE_ADD(start_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_END' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id=l.pcid
            WHERE g.usrgruid IN (
                SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                UNION
                SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                WHERE g1.parentusrgruid = 1002
            )
            AND start_date BETWEEN ? AND ?
            {$whereConditions}
            
            UNION ALL
            
            SELECT
                DATE_ADD(end_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_START' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id=l.pcid
            WHERE g.usrgruid IN (
                SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                UNION
                SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                WHERE g1.parentusrgruid = 1002
            )
            AND start_date BETWEEN ? AND ?
            {$whereConditions}
        ", $allParams);
        }

        elseif ($category === 'doctorants') {
    // Build dynamic WHERE conditions for doctorants
    $whereConditions = "";
    $params = [$startDate, $endDate];
    
    if ($groupId && $groupId !== '' && $groupId !== 'all') {
        $whereConditions .= " AND g.usrgruid = ?";
        $params[] = $groupId;
    }
    
    if ($personId && $personId !== '' && $personId !== 'all') {
        $whereConditions .= " AND u.usrid = ?";
        $params[] = $personId;
    }

    // Build parameters for each part of the doctorants UNION query
    $modifiedPunchParams = [$startDate, $endDate];
    $leaveParams1 = [$startDate, $endDate];
    $leaveParams2 = [$startDate, $endDate];
    
    if ($groupId && $groupId !== '' && $groupId !== 'all') {
        $modifiedPunchParams[] = $groupId;
        $leaveParams1[] = $groupId;
        $leaveParams2[] = $groupId;
    }
    
    if ($personId && $personId !== '' && $personId !== 'all') {
        $modifiedPunchParams[] = $personId;
        $leaveParams1[] = $personId;
        $leaveParams2[] = $personId;
    }

    // Build all parameters array for the doctorants UNION query
    $allParams = array_merge(
        $params, // First query params
        $modifiedPunchParams, // Modified punch log params
        $leaveParams1, // First leave query params
        $leaveParams2 // Second leave query params
    );

    $results = DB::connection('biostar_tna')->select("
         SELECT
                 DATE_ADD(l.devdt, INTERVAL 1 HOUR) AS srvdt,
                CAST(l.devid AS CHAR) AS devuid,
                CAST(u.usrid AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Original' AS record_type
            FROM punchlog l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (1004, 13100)
            AND l.devid IN (
                546271448, 546271450, 546271459, 546271456,
                546271451, 546271446, 546271441, 546271465,
                546271439, 546271455, 546271457, 546271449
            )
            AND l.bsevtc = 4102
            AND l.devdt BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(device_datetime, INTERVAL 1 HOUR) AS srvdt,
                CAST(type AS CHAR) AS devuid,
                CAST(USER_ID AS CHAR) AS usrid,
                CAST(user_name AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Modified' AS record_type
            FROM modifiedpunchlog
            JOIN biostar2_ac.t_usr u 
            ON USER_ID COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g 
            ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (1004, 13100)
            AND device_datetime BETWEEN ? AND ?
            {$whereConditions}
            
            UNION ALL
            
            SELECT
                DATE_ADD(start_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_END' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id=l.pcid
            WHERE g.usrgruid IN (1004, 13100)
            AND start_date BETWEEN ? AND ?
            {$whereConditions}
            
            UNION ALL
            
            SELECT
                DATE_ADD(end_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_START' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id=l.pcid
            WHERE g.usrgruid IN (1004, 13100)
            AND start_date BETWEEN ? AND ?
            {$whereConditions}
            
        ", $allParams);
}
        elseif ($category === 'complex') {
        // Same query as personnel — processing is handled by attendance_processor1.py
        $whereConditions = "";
        $params = [$startDate, $endDate];

        if ($groupId && $groupId !== '' && $groupId !== 'all') {
            $whereConditions .= " AND g.usrgruid = ?";
            $params[] = $groupId;
        }

        if ($personId && $personId !== '' && $personId !== 'all') {
            $whereConditions .= " AND u.usrid = ?";
            $params[] = $personId;
        }

        $modifiedPunchParams = [$startDate, $endDate];
        $leaveParams1 = [$startDate, $endDate];
        $leaveParams2 = [$startDate, $endDate];

        if ($groupId && $groupId !== '' && $groupId !== 'all') {
            $modifiedPunchParams[] = $groupId;
            $leaveParams1[] = $groupId;
            $leaveParams2[] = $groupId;
        }

        if ($personId && $personId !== '' && $personId !== 'all') {
            $modifiedPunchParams[] = $personId;
            $leaveParams1[] = $personId;
            $leaveParams2[] = $personId;
        }

        $allParams = array_merge($params, $modifiedPunchParams, $leaveParams1, $leaveParams2);

        $results = DB::connection('biostar_tna')->select("
            SELECT
                DATE_ADD(l.devdt, INTERVAL 1 HOUR) AS srvdt,
                CAST(l.devid AS CHAR) AS devuid,
                CAST(u.usrid AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Original' AS record_type
            FROM punchlog l
            JOIN biostar2_ac.t_usr u
            ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g
            ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid = 1072
            AND l.devid IN (
                546271448, 546271450, 546271459, 546271456,
                546271451, 546271446, 546271441, 546271465,
                546271439, 546271455, 546271457, 546271449
            )
            AND l.bsevtc = 4102
            AND l.devdt BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(device_datetime, INTERVAL 1 HOUR) AS srvdt,
                CAST(type AS CHAR) AS devuid,
                CAST(USER_ID AS CHAR) AS usrid,
                CAST(user_name AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Modified' AS record_type
            FROM modifiedpunchlog
            JOIN biostar2_ac.t_usr u
            ON USER_ID COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g
            ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid = 1072
            AND device_datetime BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(start_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_END' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id = l.pcid
            WHERE g.usrgruid = 1072
            AND start_date BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(end_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_START' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id = l.pcid
            WHERE g.usrgruid = 1072
            AND start_date BETWEEN ? AND ?
            {$whereConditions}
        ", $allParams);
        }
        elseif ($category === 'vacataire_emadu') {
        // Fetch IDs from local DB, then pass to BioStar query
        $eaduIds = DB::connection('mysql')->table('emadu.vacataires_emadu')
            ->whereNotNull('id_biostar')
            ->pluck('id_biostar')
            ->map(fn($id) => (string)$id)
            ->unique()
            ->values()
            ->toArray();

        $eaduIdList = !empty($eaduIds) ? implode(',', array_map('intval', $eaduIds)) : '0';

        $whereConditions = "";
        $params = [$startDate, $endDate];

        if ($personId && $personId !== '' && $personId !== 'all') {
            $whereConditions .= " AND u.usrid = ?";
            $params[] = $personId;
        }

        $modifiedPunchParams = [$startDate, $endDate];
        $leaveParams1 = [$startDate, $endDate];
        $leaveParams2 = [$startDate, $endDate];

        if ($personId && $personId !== '' && $personId !== 'all') {
            $modifiedPunchParams[] = $personId;
            $leaveParams1[] = $personId;
            $leaveParams2[] = $personId;
        }

        $allParams = array_merge($params, $modifiedPunchParams, $leaveParams1, $leaveParams2);

        $emadu_filter = "u.usrid IN ({$eaduIdList})";

        $results = DB::connection('biostar_tna')->select("
            SELECT
                DATE_ADD(l.devdt, INTERVAL 1 HOUR) AS srvdt,
                CAST(l.devid AS CHAR) AS devuid,
                CAST(u.usrid AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Original' AS record_type
            FROM punchlog l
            JOIN biostar2_ac.t_usr u
            ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g
            ON u.usrgruid = g.usrgruid
            WHERE {$emadu_filter}
            AND l.devid IN (
                546271448, 546271450, 546271459, 546271456,
                546271451, 546271446, 546271441, 546271465,
                546271439, 546271455, 546271457, 546271449
            )
            AND l.bsevtc = 4102
            AND l.devdt BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(device_datetime, INTERVAL 1 HOUR) AS srvdt,
                CAST(type AS CHAR) AS devuid,
                CAST(USER_ID AS CHAR) AS usrid,
                CAST(user_name AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                'Modified' AS record_type
            FROM modifiedpunchlog
            JOIN biostar2_ac.t_usr u
            ON USER_ID COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g
            ON u.usrgruid = g.usrgruid
            WHERE {$emadu_filter}
            AND device_datetime BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(start_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_END' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id = l.pcid
            WHERE {$emadu_filter}
            AND start_date BETWEEN ? AND ?
            {$whereConditions}

            UNION ALL

            SELECT
                DATE_ADD(end_date, INTERVAL 1 HOUR) AS srvdt,
                'PUNCH_TYPE_BREAK_START' AS devuid,
                CAST(l.user_id AS CHAR) AS usrid,
                CAST(u.nm AS CHAR) AS user_name,
                CAST(g.nm AS CHAR) AS group_name,
                CAST(p.name AS CHAR) AS record_type
            FROM biostar_tna.leave l
            JOIN biostar2_ac.t_usr u ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            JOIN paycode p ON p.id = l.pcid
            WHERE {$emadu_filter}
            AND start_date BETWEEN ? AND ?
            {$whereConditions}
        ", $allParams);
        }
        // Convert objects to arrays if needed
        $results = array_map(function($item) {
            return (array)$item;
        }, $results);
        //dd($results);
        // More robust cleaning of the data to handle malformed UTF-8
        $cleanResults = array_map(function($item) {
            return array_map(function($value) {
                if (is_string($value)) {
                    // First detect encoding, then convert properly
                    $encoding = mb_detect_encoding($value, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
                    if ($encoding === false) {
                        $encoding = 'ISO-8859-1'; // Fallback encoding
                    }
                    // Convert to UTF-8, replacing invalid characters
                    $cleanValue = mb_convert_encoding($value, 'UTF-8', $encoding);
                    
                    // Additional sanitization for JSON-unfriendly characters
                    $cleanValue = preg_replace('/[\x00-\x1F\x7F]/u', '', $cleanValue);
                    return $cleanValue;
                }
                return $value;
            }, $item);
        }, $results);
        
        // Build master employee list (all employees in the selected category/group/person, even zero-punch)
        $allEmployees = $this->getEmployeeMasterList($category, $groupId ?? null, $personId ?? null);
        $publicHolidays = DB::table('public_holidays')
            ->pluck('holiday_name', 'holiday_date')
            ->mapWithKeys(fn($name, $date) => [(string) $date => $name])
            ->toArray();

        $payload = [
            'records'       => $cleanResults,
            'all_employees' => $allEmployees,
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'public_holidays' => $publicHolidays,
        ];

        // Use more strict JSON handling options
        $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        
        if ($jsonData === false) {
            return response()->json([
                'error' => 'JSON encoding failed: ' . json_last_error_msg(),
                'data_sample' => array_slice($cleanResults, 0, 3) // Show first 3 items for debugging
            ], 500);
        }
        
        // Set up parameters for the Python script 
        $tempFilePath = storage_path('app\\temp_attendance_data.json');

        // Ensure storage directory exists
        $tempDir = dirname($tempFilePath);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        file_put_contents($tempFilePath, $jsonData);
        
        // Choose script: attendance_processor1.py for 'complex', ramadan.py for everything else
        $scriptName = in_array($category, ['complex', 'vacataire_emadu']) ? 'attendance_processor1.py' : 'ramadan.py';
        $scriptPath = base_path('scripts\\' . $scriptName);

        // Set UTF-8 encoding environment variable
        putenv('PYTHONIOENCODING=utf-8');

        // Robust Python detection: env override > common paths > where > candidates
        $envCommand = env('PYTHON_COMMAND');
        $workingPython = null;

        if ($envCommand) {
            $workingPython = $envCommand;
        } else {
            $candidates = ['py -3.11', 'py -3', 'py', 'python3', 'python'];

            // Add paths found by 'where' command
            $wherePython = [];
            exec('where python 2>&1', $wherePython, $whereReturn);
            if ($whereReturn === 0) {
                foreach ($wherePython as $foundPath) {
                    $foundPath = trim($foundPath);
                    if (!empty($foundPath) && file_exists($foundPath)) {
                        $candidates[] = '"' . $foundPath . '"';
                    }
                }
            }

            // Add common installation paths
            $commonPaths = [
                'C:\\Python313\\python.exe',
                'C:\\Python312\\python.exe',
                'C:\\Python311\\python.exe',
                'C:\\Python310\\python.exe',
                'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
                'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python312\\python.exe',
                'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python311\\python.exe',
            ];
            foreach ($commonPaths as $path) {
                if (file_exists($path)) {
                    $candidates[] = '"' . $path . '"';
                }
            }

            foreach ($candidates as $cmd) {
                $testOutput = [];
                exec("$cmd --version 2>&1", $testOutput, $testReturn);
                if ($testReturn === 0) {
                    $workingPython = $cmd;
                    break;
                }
            }
        }

        if (!$workingPython) {
            return response()->json([
                'error' => 'Python not found on system',
                'message' => 'Please install Python 3 and ensure it is in PATH, or set PYTHON_COMMAND in .env',
            ], 500);
        }

        // Pass original (unextended) dates so Python can filter out midnight-crossover outliers
        $originalStart = $validated['start_date'];
        $originalEnd   = $validated['end_date'];

        // 2>&1 redirects stderr into stdout so exec() captures error messages too
        $pythonCommand = "$workingPython -X utf8 \"$scriptPath\" " . escapeshellarg($tempFilePath) . ' ' . escapeshellarg($originalStart) . ' ' . escapeshellarg($originalEnd) . " 2>&1";
        
        exec($pythonCommand, $output, $returnCode);
        
        // Only clean up the temp file if successful
        if ($returnCode === 0 && file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
        
        if ($returnCode !== 0) {
            // Check if Python script exists
            $scriptExists = file_exists($scriptPath);

            // Clean output for UTF-8 safety
            $cleanOutput = array_map(function($line) {
                if (is_string($line)) {
                    $enc = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
                    if (!$enc) $enc = 'ISO-8859-1';
                    $line = mb_convert_encoding($line, 'UTF-8', $enc);
                    $line = preg_replace('/[\x00-\x1F\x7F]/u', '', $line);
                }
                return $line;
            }, $output);
            
            return response()->json([
                'error' => 'Python script execution failed with code: ' . $returnCode,
                'output' => $cleanOutput,
                'script_exists' => $scriptExists,
                'script_path' => $scriptPath,
                'command' => $pythonCommand,
                'json_data_sample' => substr($jsonData, 0, 500)
            ], 500);
        }
        
        // Combine output lines
        $outputData = implode("\n", $output);

        // Fix UTF-8: remove BOM, detect encoding, clean
        $outputData = preg_replace('/^\xEF\xBB\xBF/', '', $outputData);
        $encoding = mb_detect_encoding($outputData, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $outputData = mb_convert_encoding($outputData, 'UTF-8', $encoding);
        }
        $outputData = mb_convert_encoding($outputData, 'UTF-8', 'UTF-8');
        if (function_exists('iconv')) {
            $outputData = @iconv('UTF-8', 'UTF-8//IGNORE', $outputData);
        }
         
        // Check if output is valid JSON
        $jsonOutput = json_decode($outputData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $cleanedOutput = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $outputData);
            $jsonOutput = json_decode($cleanedOutput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Failed to parse JSON output: ' . json_last_error_msg(),
                    'raw_output' => mb_substr($outputData, 0, 1000),
                ], 500);
            }
        }
        
        if (!isset($attendanceData['processed_data']) || empty($attendanceData['processed_data'])) {
                 $attendanceData['processed_data'] = [];
            }

        $allColumns = [
            'date', 'nom', 'id_utilisateur', 'departement', 'status', 'exception',
            'premier_entree', 'sortie_final', 'r1', 'sa1', 'r2', 'sa2',
            'sum_sa', 'sum_r', 'sum_gen', 'hour_supp'
        ];

        if (in_array($category, ['complex', 'vacataire_emadu'])) {
            $allColumns = array_values(array_diff($allColumns, ['r1', 'sa1', 'r2', 'sa2', 'sum_sa', 'sum_r', 'hour_supp']));
        }

        $selectedColumns = $request->input('columns', session('selected_columns', $allColumns));
        
        if (in_array($category, ['complex', 'vacataire_emadu'])) {
            $selectedColumns = array_values(array_diff($selectedColumns, ['r1', 'sa1', 'r2', 'sa2', 'sum_sa', 'sum_r', 'hour_supp']));
        }

        session(['selected_columns' => $selectedColumns]);
        
        return view('welcome', [
        'attendanceData' => $jsonOutput,
        'selectedColumns' => $selectedColumns,
        'start_date' => $request->input('start_date'),
        'end_date' => $request->input('end_date'),
        'category' => $category,
        'group_id' => $groupId,
        'person_id' => $personId,
        'userDroit' => $userDroit,
        'persons' => $this->loadPersonsByDroit($userDroit),
    ]);
    
    }

      public function exportExcel(Request $request)
    {
        // Validate request including filter parameters
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'category' => 'nullable|string',
            'group_id' => 'nullable|string',
            'person_id' => 'nullable|string',
        ]);

        $sessionUser = Session::get('user');
        $usesSimpleExport = $this->isSimpleAttendanceCategory($validated['category'] ?? null)
            || (int) ($sessionUser->droit ?? 1) === 3;

        if ($usesSimpleExport && $request->input('type') !== 'selected') {
            $request->merge([
                'type' => 'selected',
                'columns' => [
                    'date', 'nom', 'id_utilisateur', 'departement', 'status', 'exception',
                    'premier_entree', 'sortie_final', 'sum_gen',
                ],
            ]);
        }

        $category = $validated['category'] ?? null;
        if (in_array($category, ['complex', 'vacataire_emadu'])) {
            $allowedColumns = [
                'date', 'nom', 'id_utilisateur', 'departement', 'status', 'exception',
                'premier_entree', 'sortie_final', 'sum_gen',
                'e1', 's1', 'e2', 's2', 'e3', 's3', 'e4', 's4'
            ];
            $reqColumns = $request->input('columns');
            if (empty($reqColumns)) {
                $reqColumns = $allowedColumns;
            } else {
                $reqColumns = array_values(array_intersect($reqColumns, $allowedColumns));
            }
            $request->merge([
                'type' => 'selected',
                'columns' => $reqColumns
            ]);
        }
        
        // Get attendance data using the existing controller with filters
        $attendanceController = new AttendanceController();
        $response = $attendanceController->getAttendanceData($request);
        
        
        // Extract attendance data from the view data
        $viewData = $response->getData();
        $attendanceData = $viewData['attendanceData'] ?? Session::get('attendanceData');
        
        if (empty($attendanceData) || empty($attendanceData['processed_data'])) {
            return redirect()->back()->with('error', 'No data available to export');
        }
        
        $processed_data = $attendanceData['processed_data'];
        
        // Handle column selection if specified
        $selectedColumns = $request->input('columns', []);
        if (!empty($selectedColumns) && $request->input('type') === 'selected') {
            $processed_data = $this->filterDataByColumns($processed_data, $selectedColumns);
        }
        
        // Choose export format (CSV or Excel)
        $format = $request->input('format', 'csv');
        
        if ($format === 'excel' && class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            return $this->generateExcel($processed_data, $selectedColumns);
        } else {
            return $this->generateCSV($processed_data, $selectedColumns);
        }
    }
    
    private function generateCSV($data, $selectedColumns = [])
    {
        // Create CSV content
        $output = fopen('php://temp', 'w');
        
        // Determine headers based on selected columns or use all
        if (!empty($selectedColumns) && !empty($data)) {
            // Use actual keys from the first record (after filtering)
            $headers = array_keys($data[0]);
        } else {
            // Default headers for all columns
            $headers = [
                'Date', 'Nom', 'ID utilisateur', 'Departement', 'Status','Exception'
                ,'Premier Entree', 'Sortie Final', 'R1', 'SA1', 'R2', 'SA2',
                'SUM SA', 'SUM R', 'SUM GEN', 'Hour Supp','e1','s1','e2','s2','e3','s3','e4','s4'
            ];
        }
        fputcsv($output, $headers);
        
        // Add data rows
        foreach ($data as $record) {
            if (!empty($selectedColumns) && !empty($headers)) {
                // For filtered columns, use the actual values in order of headers
                $row = [];
                foreach ($headers as $header) {
                    $row[] = $record[$header] ?? '';
                }
            } else {
                // Default full row data
                $row = [
                    $record['Date'] ?? '',
                    $record['Nom'] ?? '',
                    $record['ID utilisateur'] ?? '',
                    $record['Département'] ?? '',
                    $record['Status'] ?? '',
                    $record['Exception'] ?? '',
                    $record['Premier Entree'] ?? '',
                    $record['Sortie Final'] ?? '',
                    $record['Retard 1 '] ?? '',
                    $record['Sortie en avance 1 '] ?? '',
                    $record['Retard 2 '] ?? '',
                    $record['Sortie en avance 2 '] ?? '',
                    $record['la somme Sortie en avance'] ?? '',
                    $record['la somme Retard'] ?? '',
                    $record['la somme du Retards General'] ?? '',
                    $record['Heure Supp'] ?? '',
                    $record['e1'] ?? '',
                    $record['s1'] ?? '',
                    $record['e2'] ?? '',
                    $record['s2'] ?? '',
                    $record['e3'] ?? '',
                    $record['s3'] ?? '',
                    $record['e4'] ?? '',
                    $record['s4'] ?? ''
                ];
            }
            fputcsv($output, $row);
        }
        
        // Reset the file pointer to beginning of file
        rewind($output);
        
        // Get the content of the CSV
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        // Generate filename
        $fileName = 'attendance_data_' . date('Y-m-d') . '.csv';
        
        // Set headers for download
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ];
        
        // Return the CSV as a download
        return response($csvContent, 200, $headers);
    }
    
    private function generateExcel($data, $selectedColumns = [])
    {
        // Create new Spreadsheet object
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Determine headers based on selected columns or use all
        if (!empty($selectedColumns) && !empty($data)) {
            // Use actual keys from the first record (after filtering)
            $headers = array_keys($data[0]);
        } else {
            // Default headers for all columns
            $headers = [
                'Date', 'Nom', 'ID utilisateur', 'Département', 'Status','Exception',
                'Premier Entrée', 'Sortie Final', 'R1', 'SA1', 'R2', 'SA2',
                'SUM SA', 'SUM R', 'SUM GEN', 'Hour Supp'
            ];
        }
        
        foreach ($headers as $index => $header) {
            $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($column . '1', $header);
            
            // Style headers
            $sheet->getStyle($column . '1')->getFont()->setBold(true);
            $sheet->getStyle($column . '1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E0E0E0');
        }
        
        // Add data rows
        $row = 2;
        foreach ($data as $record) {
            if (!empty($selectedColumns) && !empty($headers)) {
                // For filtered columns, dynamically set values based on headers
                $colIndex = 1;
                foreach ($headers as $header) {
                    $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                    $sheet->setCellValue($column . $row, $record[$header] ?? '');
                    $colIndex++;
                }
            } else {
                // Default full data
                $sheet->setCellValue('A' . $row, $record['Date'] ?? '');
                $sheet->setCellValue('B' . $row, $record['Nom'] ?? '');
                $sheet->setCellValue('C' . $row, $record['ID utilisateur'] ?? '');
                $sheet->setCellValue('D' . $row, $record['Département'] ?? '');
                $sheet->setCellValue('E' . $row, $record['Status'] ?? '');
                $sheet->setCellValue('F' . $row, $record['Exception'] ?? '');
                $sheet->setCellValue('G' . $row, $record['Premier Entree'] ?? '');
                $sheet->setCellValue('H' . $row, $record['Sortie Final'] ?? '');
                $sheet->setCellValue('I' . $row, $record['Retard 1 '] ?? '');
                $sheet->setCellValue('J' . $row, $record['Sortie en avance 1 '] ?? '');
                $sheet->setCellValue('K' . $row, $record['Retard 2 '] ?? '');
                $sheet->setCellValue('L' . $row, $record['Sortie en avance 2 '] ?? '');
                $sheet->setCellValue('M' . $row, $record['la somme Sortie en avance'] ?? '');
                $sheet->setCellValue('N' . $row, $record['la somme Retard'] ?? '');
                $sheet->setCellValue('O' . $row, $record['la somme du Retards General'] ?? '');
                $sheet->setCellValue('P' . $row, $record['Heure Supp'] ?? '');
            }
            $row++;
        }
        
        // Auto size columns
        foreach (range('A', 'O') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Create Excel file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'attendance_data_' . date('Y-m-d') . '.xlsx';
        
        // Save to output
        ob_start();
        $writer->save('php://output');
        $excelOutput = ob_get_clean();
        
        // Return the Excel file
        return response($excelOutput, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function filterDataByColumns($data, $selectedColumns)
    {
        if (empty($selectedColumns)) {
            return $data;
        }

        // Column mapping from frontend values to data keys
        $columnMapping = [
            'date' => 'Date',
            'name' => 'Nom',
            'user_id' => 'ID utilisateur',
            'department' => 'Département',
            'status' => 'Status',
            'exception' => 'Exception',
            'first_entry' => 'Premier Entree',
            'last_exit' => 'Sortie Final',
            'nom' => 'Nom',
            'id_utilisateur' => 'ID utilisateur',
            'departement' => 'Département',
            'premier_entree' => 'Premier Entree',
            'sortie_final' => 'Sortie Final',
            'late_1' => 'Retard 1 ',
            'early_exit_1' => 'Sortie en avance 1 ',
            'late_2' => 'Retard 2 ',
            'early_exit_2' => 'Sortie en avance 2 ',
            'sum_early_exit' => 'la somme Sortie en avance',
            'sum_late' => 'la somme Retard',
            'sum_gen' => 'la somme du Retards General',
            'overtime' => 'Heure Supp',
            'e1' => 'e1',
            's1' => 's1',
            'e2' => 'e2',
            's2' => 's2',
            'e3' => 'e3',
            's3' => 's3',
            'e4' => 'e4',
            's4' => 's4'
        ];

        // Filter data to only include selected columns
        return array_map(function($record) use ($selectedColumns, $columnMapping) {
            $filteredRecord = [];
            foreach ($selectedColumns as $column) {
                if (isset($columnMapping[$column]) && array_key_exists($columnMapping[$column], $record)) {
                    $filteredRecord[$columnMapping[$column]] = $record[$columnMapping[$column]];
                }
            }
            return $filteredRecord;
        }, $data);
    }

public function downloadCumulativeData(Request $request)
{
    // Validate request including filter parameters
    $validated = $request->validate([
        'start_date' => 'required|date',
        'end_date' => 'required|date|after_or_equal:start_date',
        'category' => 'nullable|string',
        'group_id' => 'nullable|string',
        'person_id' => 'nullable|string',
    ]);

    // Fetch attendance data with filters
    $attendanceController = new AttendanceController();
    $response = $attendanceController->getAttendanceData($request);
    $viewData = $response->getData();


    $attendanceData = $viewData['attendanceData'] ?? Session::get('attendanceData');
    $summaryData = $attendanceData['summary_data'] ?? null;
    if (empty($summaryData)) {
        return redirect()->back()->with('error', 'No cumulative data available to download.');
    }

    // Create CSV content
    $output = fopen('php://temp', 'w');

    // Add headers
    $headers = [
        'Nom', 'ID utilisateur', 'Departement', 'Period', 
        'Total Jours Absents', 'Total Sortie en avance', 
        'Total Retard', 'Total Retard General', 'Total Heures Supplementaires'
    ];
    fputcsv($output, $headers);

    // Add data rows
    foreach ($summaryData as $record) {
        $row = [
            $record['Nom'] ?? '',
            $record['ID utilisateur'] ?? '',
            $record['Département'] ?? '',
            $record['Period'] ?? '',
            $record['Total Jours Absents'] ?? '',
            $record['Total Sortie en avance'] ?? '',
            $record['Total Retard'] ?? '',
            $record['Total Retard General'] ?? '',
            $record['Total Heures Supplementaires'] ?? '',
        ];
        fputcsv($output, $row);
    }

    // Reset the file pointer to the beginning of the file
    rewind($output);

    // Get the content of the CSV
    $csvContent = stream_get_contents($output);
    fclose($output);

    // Generate filename
    $fileName = 'cumulative_data_' . date('Y-m-d') . '.csv';

    // Set headers for download
    $headers = [
        'Content-Type' => 'text/csv',
        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma' => 'no-cache',
        'Expires' => '0'
    ];

    // Return the CSV as a download
    return response($csvContent, 200, $headers);
}
public function logout()
{
    Session::flush();
    return redirect('/login');
}

public function showChangePasswordForm()
{
    $user = Session::get('user');
    if (!$user) {
        return redirect()->route('login')->with('error', 'Veuillez vous connecter');
    }

    return view('change-password');
}

public function changePassword(Request $request)
{
    $sessionUser = Session::get('user');
    if (!$sessionUser) {
        return redirect()->route('login')->with('error', 'Veuillez vous connecter');
    }

    $validated = $request->validate([
        'current_password' => 'required|string',
        'password' => 'required|string|min:6|confirmed',
    ]);

    $user = DB::table('users')->where('id', $sessionUser->id)->first();
    if (!$user || !password_verify($validated['current_password'], $user->password)) {
        return back()
            ->withErrors(['current_password' => 'Le mot de passe actuel est incorrect.'])
            ->withInput();
    }

    DB::table('users')->where('id', $user->id)->update([
        'password' => bcrypt($validated['password']),
        'updated_at' => now(),
    ]);

    $freshUser = DB::table('users')->where('id', $user->id)->first();
    Session::put('user', $freshUser);

    return redirect()->route('change.password')->with('success', 'Mot de passe modifie avec succes.');
}

public function showLoginForm()
{
    
    return view('login');
}

public function login(Request $request)
{
    
    // Validate input
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Fetch user by email
    $user = DB::table('users')->where('email', $request->email)->first();
   

    // Check if user exists and password matches
    if ($user && password_verify($request->password, $user->password)) {
        // Store user in session
        Session::put('user', $user);

        return redirect('/');
    }

    // Return with error
    return back()->withErrors(['login_error' => 'Invalid credentials']);
}
public function add_session()
    {
        // Check if user is logged in
        $user = Session::get('user');
        if (!$user) {
            return redirect()->route('login')->with('error', 'Veuillez vous connecter');
        }

        $userDroit = $user->droit ?? 1;

        $category = null;
        if ($userDroit == 1) {
            $category = 'personnel';
        } elseif ($userDroit == 2) {
            $category = 'doctorants';
        } elseif ($userDroit == 3) {
            $category = 'complex';
        }

        $allColumns = [
            'date', 'nom', 'id_utilisateur', 'departement', 'status', 'exception',
            'premier_entree', 'sortie_final', 'r1', 'sa1', 'r2', 'sa2',
            'sum_sa', 'sum_r', 'sum_gen', 'hour_supp'
        ];

        if (in_array($category, ['complex', 'vacataire_emadu'])) {
            $allColumns = array_values(array_diff($allColumns, ['r1', 'sa1', 'r2', 'sa2', 'sum_sa', 'sum_r', 'hour_supp']));
        }

        // Load persons from biostar DB based on user category
        $persons = $this->loadPersonsByDroit($userDroit);

        return view('welcome', [
            'selectedColumns' => $allColumns,
            'userDroit' => $userDroit,
            'persons' => $persons,
            'category' => $category,
        ]);
    }

    private function getEmployeeMasterList(string $category, ?string $groupId, ?string $personId): array
    {
        try {
            DB::statement("SET collation_connection = 'utf8_general_ci'");

            if ($category === 'personnel') {
                $groupFilter = "g.usrgruid IN (
                    SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                    UNION
                    SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                    JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                    WHERE g1.parentusrgruid = 1002
                )";
            } elseif ($category === 'doctorants') {
                $groupFilter = "g.usrgruid IN (1004, 13100)";
            } elseif ($category === 'complex') {
                $groupFilter = "g.usrgruid = 1072";
            } elseif ($category === 'vacataire_emadu') {
                $eaduIds = DB::connection('mysql')->table('emadu.vacataires_emadu')
                    ->whereNotNull('id_biostar')
                    ->pluck('id_biostar')
                    ->map(fn($id) => (string)$id)
                    ->unique()->values()->toArray();
                $eaduIdList = !empty($eaduIds) ? implode(',', array_map('intval', $eaduIds)) : '0';
                $groupFilter = "u.usrid IN ({$eaduIdList})";
            } else {
                $groupFilter = "1=1";
            }

            $extraConditions = "";
            $params = [];
            if ($groupId) {
                $extraConditions .= " AND g.usrgruid = ?";
                $params[] = $groupId;
            }
            if ($personId) {
                $extraConditions .= " AND u.usrid = ?";
                $params[] = $personId;
            }

            $employees = DB::connection('biostar_tna')->select(
                "SELECT DISTINCT
                    CAST(u.usrid AS CHAR) as user_id,
                    CAST(u.nm AS CHAR) as user_name,
                    CAST(g.nm AS CHAR) as department
                 FROM biostar2_ac.t_usr u
                 JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
                 WHERE {$groupFilter}
                 {$extraConditions}
                 ORDER BY u.nm",
                $params
            );

            return array_map(fn($e) => (array) $e, $employees);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function loadPersonsByDroit(int $userDroit): array
    {
        try {
            DB::statement("SET collation_connection = 'utf8_general_ci'");

            if ($userDroit == 1) {
                $groupFilter = "g.usrgruid IN (
                    SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                    UNION
                    SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                    JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                    WHERE g1.parentusrgruid = 1002
                )";
            } elseif ($userDroit == 2) {
                $groupFilter = "g.usrgruid IN (1004, 13100)";
            } elseif ($userDroit == 3) {
                $groupFilter = "g.usrgruid = 1072";
            } else {
                // droit == 0: all categories
                $groupFilter = "g.usrgruid IN (
                    SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                    UNION
                    SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                    JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                    WHERE g1.parentusrgruid = 1002
                    UNION SELECT 1004 UNION SELECT 13100 UNION SELECT 1072
                )";
            }

            $persons = DB::connection('biostar_tna')->select(
                "SELECT DISTINCT CAST(u.usrid AS CHAR) as user_id, CAST(u.nm AS CHAR) as user_name,
                 CASE
                     WHEN g.usrgruid IN (1004, 13100) THEN 'doctorants'
                     WHEN g.usrgruid = 1072 THEN 'complex'
                     ELSE 'personnel'
                 END as category
                 FROM biostar2_ac.t_usr u
                 JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
                 WHERE {$groupFilter}
                 ORDER BY u.nm"
            );

            return array_map(fn($p) => (array) $p, $persons);
        } catch (\Exception $e) {
            return [];
        }
    }
    public function showRegisterForm()
{
    return view('register');
}


public function register(Request $request)
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|max:255|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
    ]);

    DB::table('users')->insert([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => bcrypt($validated['password']),
        'role' => 'user',
        'droit' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return redirect()->route('login')->with('success', 'Registration successful. Please login.');
}

public function getGroups(Request $request)
{
    $category = $request->input('category');
    
    DB::statement("SET collation_connection = 'utf8_general_ci'");
    
    $query = "SELECT DISTINCT CAST(g.usrgruid AS CHAR) as group_id, CAST(g.nm AS CHAR) as group_name 
              FROM biostar2_ac.t_usrgr g 
              WHERE 1=1";
    
    $params = [];
    
    if ($category === 'personnel') {
        $query .= " AND (g.usrgruid IN (
            SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
            UNION
            SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
            JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
            WHERE g1.parentusrgruid = 1002
        ))";
    } elseif ($category === 'doctorants') {
        $query .= " AND g.usrgruid IN (1004, 13100)";
    } elseif ($category === 'complex') {
        $query .= " AND g.usrgruid = 1072";
    } elseif ($category === 'vacataire_emadu') {
        // Vacataires EMADU are identified by user IDs, not groups
        return response()->json([]);
    }
    
    $query .= " ORDER BY g.nm";
    
    $groups = DB::connection('biostar_tna')->select($query, $params);
    
    return response()->json($groups);
}

public function getPersons(Request $request)
{
    $category = $request->input('category');
    $group_id = $request->input('group_id');
    $search = $request->input('search', '');
    
    try {
        DB::connection('biostar_tna')->statement("SET collation_connection = 'utf8_general_ci'");
        
        $query = "SELECT DISTINCT CAST(u.usrid AS CHAR) as user_id, CAST(u.nm AS CHAR) as user_name 
                  FROM biostar2_ac.t_usr u 
                  JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid 
                  WHERE 1=1";
        
        $params = [];
        
        if ($category === 'personnel') {
            $query .= " AND g.usrgruid IN (
                SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                UNION
                SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                WHERE g1.parentusrgruid = 1002
            )";
        } elseif ($category === 'doctorants') {
            $query .= " AND g.usrgruid IN (1004, 13100)";
        } elseif ($category === 'complex') {
            $query .= " AND g.usrgruid = 1072";
        } elseif ($category === 'vacataire_emadu') {
            $eaduIds = DB::connection('mysql')->table('emadu.vacataires_emadu')
                ->whereNotNull('id_biostar')
                ->pluck('id_biostar')
                ->map(fn($id) => (string)$id)
                ->unique()->values()->toArray();
            $eaduIdList = !empty($eaduIds) ? implode(',', array_map('intval', $eaduIds)) : '0';
            $query .= " AND u.usrid IN ({$eaduIdList})";
        }
        
        if ($group_id) {
            $query .= " AND g.usrgruid = ?";
            $params[] = $group_id;
        }
        
        if ($search) {
            $query .= " AND u.nm LIKE ?";
            $params[] = '%' . $search . '%';
        }
        
        $query .= " ORDER BY u.nm LIMIT 20";
        
        $persons = DB::connection('biostar_tna')->select($query, $params);
        
        return response()->json($persons);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

// ─── Profile AC/AD ────────────────────────────────────────────────────────────

public function profileAcAd()
{
    // All people already categorised (local DB)
    $defined = DB::table('personnel_categorie')->orderBy('nom')->get();
    $localIds = $defined->pluck('id_biostar')->map(fn($v) => (string)$v)->toArray();

    // Fetch all personnel from BioStar
    $biostarFailed   = false;
    $biostarPersons  = [];
    try {
        DB::connection('biostar_tna')->statement("SET collation_connection = 'utf8_general_ci'");
        $rows = DB::connection('biostar_tna')->select(
            "SELECT DISTINCT CAST(u.usrid AS CHAR) as user_id, CAST(u.nm AS CHAR) as user_name
             FROM biostar2_ac.t_usr u
             JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
             WHERE g.usrgruid IN (
                 SELECT usrgruid FROM biostar2_ac.t_usrgr WHERE parentusrgruid = 1002
                 UNION
                 SELECT g2.usrgruid FROM biostar2_ac.t_usrgr g1
                 JOIN biostar2_ac.t_usrgr g2 ON g2.parentusrgruid = g1.usrgruid
                 WHERE g1.parentusrgruid = 1002
             )
             ORDER BY u.nm"
        );
        $biostarPersons = array_map(fn($p) => (array)$p, $rows);
    } catch (\Exception $e) {
        $biostarFailed = true;
    }

    // People in BioStar but NOT in local personnel_categorie
    $undefined = array_values(array_filter(
        $biostarPersons,
        fn($p) => !in_array((string)$p['user_id'], $localIds)
    ));

    // Combined list for JS search
    $allPersonsForSearch = array_merge(
        $defined->map(function($p) {
            return ['user_id' => (string)$p->id_biostar, 'user_name' => $p->nom, 'defined' => true];
        })->values()->toArray(),
        array_map(function($p) {
            return ['user_id' => $p['user_id'], 'user_name' => $p['user_name'], 'defined' => false];
        }, $undefined)
    );

    return view('admin.profile-acad', [
        'defined'             => $defined,
        'undefined'           => $undefined,
        'allPersonsForSearch' => $allPersonsForSearch,
        'biostarFailed'       => $biostarFailed,
    ]);
}

public function storeProfileAcAd(Request $request)
{
    $validated = $request->validate([
        'id_biostar' => 'required|integer',
        'nom'        => 'required|string|max:255',
        'categorie'  => 'required|in:AC,AD',
    ]);

    $existing = DB::table('personnel_categorie')
        ->where('id_biostar', $validated['id_biostar'])
        ->first();

    if ($existing) {
        return redirect()->route('admin.profile-acad')
            ->with('error', "Ce personnel existe déjà (ID BioStar : {$existing->id_biostar} — {$existing->nom}, catégorie : {$existing->categorie}).");
    }

    DB::table('personnel_categorie')->insert([
        'id_biostar' => $validated['id_biostar'],
        'nom'        => $validated['nom'],
        'categorie'  => $validated['categorie'],
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return redirect()->route('admin.profile-acad')->with('success', 'Personnel ajouté avec succès.');
}

public function updateProfileAcAd(Request $request, int $id)
{
    $validated = $request->validate([
        'categorie' => 'required|in:AC,AD',
    ]);

    DB::table('personnel_categorie')
        ->where('id', $id)
        ->update(['categorie' => $validated['categorie'], 'updated_at' => now()]);

    return redirect()->route('admin.profile-acad')->with('success', 'Catégorie mise à jour.');
}

public function destroyProfileAcAd(int $id)
{
    DB::table('personnel_categorie')->where('id', $id)->delete();

    return redirect()->route('admin.profile-acad')->with('success', 'Personnel supprimé.');
}

// ─── Cumul Personnel (Rapport de synthèse RH) ────────────────────────────────

public function downloadCumulPersonnel(Request $request)
{
    $validated = $request->validate([
        'start_date' => 'required|date',
        'end_date'   => 'required|date|after_or_equal:start_date',
        'category'   => 'nullable|string',
        'group_id'   => 'nullable|string',
        'person_id'  => 'nullable|string',
    ]);

    // Re-use existing pipeline to get processed data
    $attendanceController = new AttendanceController();
    $response    = $attendanceController->getAttendanceData($request);
    $viewData    = $response->getData();
    $attendanceData = $viewData['attendanceData'] ?? Session::get('attendanceData');
    $summaryData    = $attendanceData['summary_data'] ?? null;

    if (empty($summaryData)) {
        return redirect()->back()->with('error', 'Aucune donnée cumulative disponible.');
    }

    // Build a lookup: id_biostar => categorie (AC / AD)
    $categories = DB::table('personnel_categorie')
        ->pluck('categorie', 'id_biostar')
        ->mapWithKeys(fn($cat, $id) => [(string)$id => $cat])
        ->toArray();

    // Helper: convert minutes (float) to "HH:MM:SS" string (same format as Python output)
    $toHhMm = function($totalMinutes) {
        $totalMinutes = (float) $totalMinutes;
        if ($totalMinutes <= 0) return '00:00:00';
        $secs  = (int) round($totalMinutes * 60);
        $h     = intdiv($secs, 3600);
        $m     = intdiv($secs % 3600, 60);
        $s     = $secs % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    };

    // Helper: convert minutes to number-of-days using the threshold rule
    // <4h=0, 4h–<8h=0.5, 8h–<12h=1, 12h–<16h=1.5, … (+0.5 day per 4h above the 4h threshold)
    $toConvertedDays = function($totalMinutes) {
        $h = $totalMinutes / 60.0;
        if ($h < 4) return 0;
        return 0.5 * (1 + (int) floor(($h - 4) / 4));
    };

    // Build rows
    $rows = [];
    foreach ($summaryData as $rec) {
        $matricule   = $rec['ID utilisateur'] ?? '';
        $entite      = $categories[(string)$matricule] ?? 'non défini';
        $nom         = $rec['Nom'] ?? '';

        // Time values from Python come as "HH:MM:SS" (e.g. "01:30:00")
        $parseMinutes = function($val) {
            $val = trim((string)$val);
            if (!$val || $val === '00:00:00') return 0;
            // HH:MM:SS format
            if (preg_match('/^(\d+):(\d{2}):(\d{2})$/', $val, $m)) {
                return (int)$m[1] * 60 + (int)$m[2] + (int)$m[3] / 60;
            }
            // Fallback: plain numeric (already minutes)
            if (is_numeric($val)) return (float)$val;
            return 0;
        };

        $cumulGlobalMin  = $parseMinutes($rec['Total Retard General'] ?? 0);
        $heureSupMin     = $parseMinutes($rec['Total Heures Supplementaires'] ?? 0);
        $cumulJustifMin  = 0; // always 0 per spec

        $cumulNonJustifMin = $cumulGlobalMin - $cumulJustifMin;          // col 7

        // Col 8: if AC → subtract heures sup, otherwise keep col 7
        $cumulNonJustifHsuppMin = ($entite === 'AC')
            ? max(0, $cumulGlobalMin - $heureSupMin)
            : $cumulNonJustifMin;

        // Col 10: days converted from col 8
        $cumulConvertedDays = $toConvertedDays($cumulNonJustifHsuppMin);

        // Col 11: absent days (already a count in summary)
        $absentsJours = $rec['Total Jours Absents'] ?? 0;

        // Col 12: total
        $total = $cumulConvertedDays + (float)$absentsJours;

        $rows[] = [
            $matricule,                              // 1 Matricule
            $entite,                                 // 2 Entité d'attache
            $nom,                                    // 3 Nom et prénom
            $toHhMm($cumulGlobalMin),                // 4 Cumul global retards+sorties
            $toHhMm($heureSupMin),                   // 5 Heure sup
            $toHhMm($cumulJustifMin),                // 6 Cumul R/S justifiés (0)
            $toHhMm($cumulNonJustifMin),             // 7 Cumul R/S non justifiés en heures
            $toHhMm($cumulNonJustifHsuppMin),        // 8 Cumul R/S non justifiés - H SUPP
            '',                                      // 9 (vide)
            $cumulConvertedDays,                     // 10 Cumul R/S non justifiés convertis en jours
            $absentsJours,                           // 11 Cumul jours absences non justifiés
            $total,                                  // 12 Total
            '',                                      // 13 Observations
        ];
    }

    // Sort by entite then nom
    usort($rows, fn($a, $b) => [$a[1], $a[2]] <=> [$b[1], $b[2]]);

    // Build CSV
    $output = fopen('php://temp', 'w');

    // UTF-8 BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    fputcsv($output, [
        'Matricule',
        'Entité d\'attache',
        'Nom et prénom',
        'Cumul global des retards et sorties en avance',
        'Heure sup',
        'Cumul R/S justifiés',
        'Cumul R/S non justifiés en heures',
        'Cumul R/S non justifiés - H SUPP',
        '',
        'Cumul R/S non justifiés convertis en jours',
        'Cumul des jours d\'absences non justifiés',
        'Total',
        'Observations',
    ], ';');

    foreach ($rows as $row) {
        fputcsv($output, $row, ';');
    }

    rewind($output);
    $csvContent = stream_get_contents($output);
    fclose($output);

    $fileName = 'cumul_personnel_' . $validated['start_date'] . '_' . $validated['end_date'] . '.csv';

    return response($csvContent, 200, [
        'Content-Type'        => 'text/csv; charset=UTF-8',
        'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        'Pragma'              => 'no-cache',
        'Expires'             => '0',
    ]);
}

    // =========================================================================
    // Attendance cache helpers
    // =========================================================================

    /**
     * Try to serve attendance data from the nightly pre-computed cache.
     *
     * Returns the {processed_data, summary_data} array on cache hit, or null
     * if the cache is absent / stale / does not fully cover the requested range.
     */
    private function getCachedAttendanceResult(
        string $category,
        string $startDate,
        string $endDate,
        ?string $groupId  = null,
        ?string $personId = null
    ): ?array {
        // Group-filtered requests need a subset that isn't stored in the cache
        if ($groupId && $groupId !== '' && $groupId !== 'all') {
            return null;
        }

        // Only cache recent ranges (last 62 days)
        $cacheStart = date('Y-m-d', strtotime('-62 days'));
        if ($startDate < $cacheStart) {
            return null;
        }

        // Verify the cache actually covers the requested end date
        $maxCached = DB::table('attendance_cache')
            ->where('category', $category)
            ->max('work_date');

        if (!$maxCached || $endDate > $maxCached) {
            return null;
        }

        $minCached = DB::table('attendance_cache')
            ->where('category', $category)
            ->min('work_date');

        if (!$minCached || $startDate < $minCached) {
            return null;
        }

        // Pull rows; optionally filter by employee
        $query = DB::table('attendance_cache')
            ->where('category', $category)
            ->whereBetween('work_date', [$startDate, $endDate]);

        if ($personId && $personId !== '' && $personId !== 'all') {
            $query->where('employee_id', $personId);
        }

        $rows = $query->get(['row_json']);

        if ($rows->isEmpty()) {
            return null;
        }

        $processedData = [];
        foreach ($rows as $row) {
            $record = json_decode($row->row_json, true);
            if ($record) {
                $processedData[] = $this->clearPunchesForSpecialRecord($record);
            }
        }

        // Sort by date then name (mirror Python output order)
        usort($processedData, fn($a, $b) =>
            strcmp(($a['Date'] ?? '') . ($a['Nom'] ?? ''), ($b['Date'] ?? '') . ($b['Nom'] ?? ''))
        );

        $summaryData = $this->buildSummaryFromRows($processedData, $startDate, $endDate);

        return [
            'processed_data' => $processedData,
            'summary_data'   => $summaryData,
        ];
    }

    /**
     * Rebuild summary_data from an array of daily per-employee rows.
     * Mirrors the Python generate_summary_report() function.
     */
    private function buildSummaryFromRows(array $processedData, string $startDate, string $endDate): array
    {
        $groups = [];

        foreach ($processedData as $row) {
            $key = (string) ($row['ID utilisateur'] ?? '');
            if (!$key) {
                continue;
            }

            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'Nom'           => $row['Nom'] ?? '',
                    'id'            => $key,
                    'Département'   => $row['Département'] ?? '',
                    'absent'        => 0,
                    'sortie_avance' => 0,
                    'retard'        => 0,
                    'retard_gen'    => 0,
                    'heures_supp'   => 0,
                ];
            }

            $status    = $row['Status'] ?? '';
            $exception = $row['Exception'] ?? '';

            if ($status === 'Absent' && empty($exception)) {
                $groups[$key]['absent']++;
            }

            $groups[$key]['sortie_avance'] += $this->hmsToSeconds($row['la somme Sortie en avance'] ?? '00:00:00');
            $groups[$key]['retard']        += $this->hmsToSeconds($row['la somme Retard'] ?? '00:00:00');
            $groups[$key]['retard_gen']    += $this->hmsToSeconds($row['la somme du Retards General'] ?? '00:00:00');
            $groups[$key]['heures_supp']   += $this->hmsToSeconds($row['Heure Supp'] ?? '00:00:00');
        }

        $summary = [];
        foreach ($groups as $g) {
            $summary[] = [
                'Nom'                           => $g['Nom'],
                'ID utilisateur'                => $g['id'],
                'Département'                   => $g['Département'],
                'Period'                        => "{$startDate} to {$endDate}",
                'Total Jours Absents'           => $g['absent'],
                'Total Sortie en avance'        => $this->secondsToHms($g['sortie_avance']),
                'Total Retard'                  => $this->secondsToHms($g['retard']),
                'Total Retard General'          => $this->secondsToHms($g['retard_gen']),
                'Total Heures Supplementaires'  => $this->secondsToHms($g['heures_supp']),
            ];
        }

        return $summary;
    }

    private function hmsToSeconds(string $hms): int
    {
        if (!$hms || $hms === '00:00:00') {
            return 0;
        }
        $parts = explode(':', $hms);
        return (int) ($parts[0] ?? 0) * 3600
             + (int) ($parts[1] ?? 0) * 60
             + (int) ($parts[2] ?? 0);
    }

    private function secondsToHms(int $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00:00';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }

}
