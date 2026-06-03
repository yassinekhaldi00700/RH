<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Session;

class AttendanceController extends Controller
{
    
    public function getAttendanceData(Request $request)
    {
        // Check if user is logged in
        $user = Session::get('user');
        if (!$user) {
            return redirect()->route('login')->with('error', 'Veuillez vous connecter');
        }
        
        // Get user rights (droit): 1 = personnel only, 2 = doctorants only, 3 = complex (both)
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
    
    // Validate category access based on user rights
    if ($userDroit == 1 && $category !== 'personnel') {
        $category = 'personnel'; // Force personnel if user only has droit 1
    } elseif ($userDroit == 2 && $category !== 'doctorants') {
        $category = 'doctorants'; // Force doctorants if user only has droit 2
    } elseif ($userDroit == 3 && $category !== 'complex') {
        $category = 'complex'; // Force complex if user only has droit 3
    }
    
    // Debug: Log the received parameters (comment out for production)
    // \Log::info('Filter Parameters:', [
    //     'category' => $category,
    //     'group_id' => $groupId,
    //     'person_id' => $personId,
    //     'all_request' => $request->all()
    // ]);
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
            WHERE device_datetime BETWEEN ? AND ?
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
            WHERE start_date BETWEEN ? AND ?
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
            WHERE start_date BETWEEN ? AND ?
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
            JOIN biostar2_ac.t_usr u 
            ON l.user_id COLLATE utf8_general_ci = u.usrid COLLATE utf8_general_ci
            JOIN biostar2_ac.t_usrgr g 
            ON u.usrgruid = g.usrgruid
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
    // Build dynamic WHERE conditions for complex (group ID 1072)
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

    // Build parameters for each part of the complex UNION query
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

    // Build all parameters array for the complex UNION query
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
            JOIN paycode p ON p.id=l.pcid
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
            JOIN paycode p ON p.id=l.pcid
            WHERE g.usrgruid = 1072
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
        
        $publicHolidays = DB::table('public_holidays')
            ->pluck('holiday_name', 'holiday_date')
            ->mapWithKeys(fn($name, $date) => [(string) $date => $name])
            ->toArray();

        $payload = [
            'records' => $cleanResults,
            'public_holidays' => $publicHolidays,
        ];

        // Use more strict JSON handling options
        $jsonData = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        
        if ($jsonData === false) {
            // Apply even more aggressive UTF-8 cleaning for error response
            $safeDataSample = array_slice($cleanResults, 0, 3);
            $safeDataSample = array_map(function($item) {
                return array_map(function($value) {
                    if (is_string($value)) {
                        // Force to valid UTF-8
                        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                        // Remove all non-printable characters
                        $value = preg_replace('/[^\x20-\x7E\x{00A0}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}]/u', '', $value);
                        return $value;
                    }
                    return $value;
                }, $item);
            }, $safeDataSample);
            
            return response()->json([
                'error' => 'JSON encoding failed: ' . json_last_error_msg(),
                'data_sample' => $safeDataSample
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
        
        // Define Python script path based on category
        // Use simplified processor for 'complex' category, standard for others
        $scriptFilename = ($category === 'complex') ? 'attendance_processor1.py' : 'attendance_processor.py';
        $scriptPath = 'C:\\xampp\\htdocs\\RH\\scripts\\' . $scriptFilename;
        
        // Set UTF-8 encoding environment variables
        putenv('PYTHONIOENCODING=utf-8');
        
        // Try to find a working Python executable
        // First try to locate python using 'where' command
        $wherePython = [];
        exec('where python 2>&1', $wherePython, $whereReturn);
        
        $pythonExecutables = [
            'py -3',        // Windows Python Launcher (most reliable on Windows)
            'py',
            'python3',
            'python',
        ];
        
        // Add paths found by 'where' command
        if ($whereReturn === 0 && !empty($wherePython)) {
            foreach ($wherePython as $foundPath) {
                $foundPath = trim($foundPath);
                if (!empty($foundPath) && file_exists($foundPath)) {
                    $pythonExecutables[] = '"' . $foundPath . '"';
                }
            }
        }
        
        // Add common installation paths for Python 3.13
        $commonPaths = [
            'C:\\Python313\\python.exe',
            'C:\\Python312\\python.exe',
            'C:\\Python311\\python.exe',
            'C:\\Python310\\python.exe',
            'C:\\Users\\talent\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            'C:\\Users\\DELL\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
            'C:\\Users\\' . get_current_user() . '\\AppData\\Local\\Programs\\Python\\Python313\\python.exe',
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                $pythonExecutables[] = '"' . $path . '"';
            }
        }
        
        $workingPython = null;
        $testResults = [];
        foreach ($pythonExecutables as $pythonExec) {
            $testCmd = "$pythonExec --version 2>&1";
            $testOutput = [];
            exec($testCmd, $testOutput, $testReturn);
            $testResults[$pythonExec] = [
                'return_code' => $testReturn,
                'output' => implode(' ', $testOutput)
            ];
            if ($testReturn === 0) {
                $workingPython = $pythonExec;
                break;
            }
        }
        
        if (!$workingPython) {
            return response()->json([
                'error' => 'Python not found on system',
                'message' => 'Python 3.13.2 is installed but PHP cannot access it. This is a PATH issue with XAMPP/Apache.',
                'tried_commands' => array_keys($testResults),
                'test_results' => $testResults,
                'where_python_result' => $wherePython,
                'where_return_code' => $whereReturn,
                'help' => 'Try restarting Apache in XAMPP or run in PowerShell: [Environment]::SetEnvironmentVariable("Path", $env:Path, "Machine")'
            ], 500);
        }
        
        if (!$workingPython) {
            return response()->json([
                'error' => 'Python not found on system',
                'message' => 'Please install Python 3 from https://www.python.org/downloads/ and ensure "Add Python to PATH" is checked during installation',
                'tried_commands' => $pythonExecutables
            ], 500);
        }
        
        // Use the working Python command
        $pythonCommand = "$workingPython -X utf8 \"$scriptPath\" " . escapeshellarg($tempFilePath) . " 2>&1";
       
        $output = [];
        $returnCode = 0;
        
        exec($pythonCommand, $output, $returnCode);
        
        // Only clean up the temp file if successful
        if ($returnCode === 0 && file_exists($tempFilePath)) {
            unlink($tempFilePath);
        }
        
        if ($returnCode !== 0) {
            // Check if Python script exists at the CORRECT path
            $scriptExists = file_exists($scriptPath);
            
            // Clean output array for UTF-8 safety
            $cleanOutput = array_map(function($line) {
                if (is_string($line)) {
                    $encoding = mb_detect_encoding($line, ['UTF-8', 'ISO-8859-1', 'ASCII'], true);
                    if ($encoding === false) $encoding = 'ISO-8859-1';
                    $line = mb_convert_encoding($line, 'UTF-8', $encoding);
                    $line = preg_replace('/[\x00-\x1F\x7F]/u', '', $line);
                    return $line;
                }
                return $line;
            }, $output);
            
            // Clean JSON data sample
            $safeJsonSample = substr($jsonData, 0, 500);
            $safeJsonSample = mb_convert_encoding($safeJsonSample, 'UTF-8', 'UTF-8');
            $safeJsonSample = preg_replace('/[^\x20-\x7E\x{00A0}-\x{00FF}\x{0100}-\x{017F}\x{0180}-\x{024F}]/u', '', $safeJsonSample);
            
            return response()->json([
                'error' => 'Python script execution failed with code: ' . $returnCode,
                'output' => $cleanOutput,
                'script_exists' => $scriptExists,
                'script_path' => $scriptPath,
                'command' => $pythonCommand,
                'temp_file_exists' => file_exists($tempFilePath),
                'json_data_sample' => $safeJsonSample
            ], 500);
        }
        
        // Combine output lines if needed
        $outputData = implode("\n", $output);
        
        // Fix UTF-8 encoding issues
        // 1. Remove BOM if present
        $outputData = preg_replace('/^\xEF\xBB\xBF/', '', $outputData);
        
        // 2. Detect and convert encoding
        $encoding = mb_detect_encoding($outputData, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'CP1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $outputData = mb_convert_encoding($outputData, 'UTF-8', $encoding);
        }
        
        // 3. Clean invalid UTF-8
        $outputData = mb_convert_encoding($outputData, 'UTF-8', 'UTF-8');
        
        // 4. Remove non-UTF-8 characters
        if (function_exists('iconv')) {
            $outputData = @iconv('UTF-8', 'UTF-8//IGNORE', $outputData);
        }
         
        // Check if output is valid JSON
        $jsonOutput = json_decode($outputData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try with control character removal
            $cleanedOutput = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $outputData);
            $jsonOutput = json_decode($cleanedOutput, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json([
                    'error' => 'Failed to parse JSON output: ' . json_last_error_msg(),
                    'raw_output' => mb_substr($outputData, 0, 1000),
                    'detected_encoding' => $encoding,
                    'output_length' => strlen($outputData),
                    'first_chars_hex' => bin2hex(substr($outputData, 0, 100))
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

    $selectedColumns = $request->input('columns', session('selected_columns', $allColumns));
    
    session(['selected_columns' => $selectedColumns]);
        
        return view('welcome', [
        'attendanceData' => $jsonOutput,
        'selectedColumns' => $selectedColumns,
        'start_date' => $request->input('start_date'),
        'end_date' => $request->input('end_date'),
        'category' => $category,
        'group_id' => $groupId,
        'person_id' => $personId,
        'userDroit' => $userDroit, // Pass user rights to view
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
            'late_1' => 'Retard 1 ',
            'early_exit_1' => 'Sortie en avance 1 ',
            'late_2' => 'Retard 2 ',
            'early_exit_2' => 'Sortie en avance 2 ',
            'sum_early_exit' => 'la somme Sortie en avance',
            'sum_late' => 'la somme Retard',
            'sum_general' => 'la somme du Retards General',
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
                if (isset($columnMapping[$column]) && isset($record[$columnMapping[$column]])) {
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
    
         $allColumns = [
    'date', 'nom', 'id_utilisateur', 'departement', 'status', 'exception',
    'premier_entree', 'sortie_final', 'r1', 'sa1', 'r2', 'sa2',
    'sum_sa', 'sum_r', 'sum_gen', 'hour_supp'
    ];

    
    
    
        
        return view('welcome', [
        
        'selectedColumns' => $allColumns,
        'userDroit' => $userDroit, // Pass user rights to view
         // or $cleanResults if you prefer
    ]);
        
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
    
    DB::statement("SET collation_connection = 'utf8_general_ci'");
    
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
}

}
