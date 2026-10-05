<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class ScheduleManagementController extends Controller
{
    private function normalizeEmployeeCode($value)
    {
        $value = trim((string) $value);
        return mb_substr($value, 0, 50);
    }

    private function checkAdmin()
    {
        $user = Session::get('user');
        if (!$user || $user->role !== 'admin') {
            return redirect()->route('login')->with('error', 'Accès non autorisé');
        }
        return null;
    }

    private function checkSuperAdmin()
    {
        $user = Session::get('user');
        if (!$user || $user->role !== 'admin' || (int) ($user->droit ?? 1) !== 0) {
            return redirect()->route('admin.dashboard')->with('error', 'Acces non autorise');
        }
        return null;
    }

    // ==================== SCHEDULES ====================
    
    public function schedules(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;
        
        $schedules = DB::table('schedules')
            ->where('ramadan', $ramadanFlag)
            ->orderBy('name')
            ->get();
            
        return view('admin.schedules.index', compact('schedules'));
    }

    public function createSchedule(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $type = $request->input('type', 'normal');
        return view('admin.schedules.create', compact('type'));
    }

    public function storeSchedule(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:schedules,name',
            'morning_start' => 'required|date_format:H:i:s',
            'lunch_start' => 'required|date_format:H:i:s',
            'lunch_end' => 'required|date_format:H:i:s',
            'workday_end' => 'required|date_format:H:i:s',
            'morning_threshold' => 'required|date_format:H:i:s',
            'lunch_threshold' => 'required|date_format:H:i:s',
            'afternoon_threshold' => 'required|date_format:H:i:s',
            'evening_threshold' => 'required|date_format:H:i:s',
            'morning_penalty' => 'required|integer|min:0',
            'lunch_penalty' => 'required|integer|min:0',
            'afternoon_penalty' => 'required|integer|min:0',
        ]);

        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;

        DB::table('schedules')->insert([
            'name' => $validated['name'],
            'morning_start' => $validated['morning_start'],
            'lunch_start' => $validated['lunch_start'],
            'lunch_end' => $validated['lunch_end'],
            'workday_end' => $validated['workday_end'],
            'morning_threshold' => $validated['morning_threshold'],
            'lunch_threshold' => $validated['lunch_threshold'],
            'afternoon_threshold' => $validated['afternoon_threshold'],
            'evening_threshold' => $validated['evening_threshold'],
            'morning_penalty' => $validated['morning_penalty'],
            'lunch_penalty' => $validated['lunch_penalty'],
            'afternoon_penalty' => $validated['afternoon_penalty'],
            'ramadan' => $ramadanFlag,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.schedules', ['type' => $type])->with('success', 'Horaire créé avec succès');
    }

    public function editSchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $schedule = DB::table('schedules')->where('id', $id)->first();
        if (!$schedule) {
            return redirect()->route('admin.schedules')->with('error', 'Horaire non trouvé');
        }
        
        return view('admin.schedules.edit', compact('schedule'));
    }

    public function updateSchedule(Request $request, $id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:schedules,name,' . $id,
            'morning_start' => 'required|date_format:H:i:s',
            'lunch_start' => 'required|date_format:H:i:s',
            'lunch_end' => 'required|date_format:H:i:s',
            'workday_end' => 'required|date_format:H:i:s',
            'morning_threshold' => 'required|date_format:H:i:s',
            'lunch_threshold' => 'required|date_format:H:i:s',
            'afternoon_threshold' => 'required|date_format:H:i:s',
            'evening_threshold' => 'required|date_format:H:i:s',
            'morning_penalty' => 'required|integer|min:0',
            'lunch_penalty' => 'required|integer|min:0',
            'afternoon_penalty' => 'required|integer|min:0',
        ]);

        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;

        DB::table('schedules')->where('id', $id)->update([
            'name' => $validated['name'],
            'morning_start' => $validated['morning_start'],
            'lunch_start' => $validated['lunch_start'],
            'lunch_end' => $validated['lunch_end'],
            'workday_end' => $validated['workday_end'],
            'morning_threshold' => $validated['morning_threshold'],
            'lunch_threshold' => $validated['lunch_threshold'],
            'afternoon_threshold' => $validated['afternoon_threshold'],
            'evening_threshold' => $validated['evening_threshold'],
            'morning_penalty' => $validated['morning_penalty'],
            'lunch_penalty' => $validated['lunch_penalty'],
            'afternoon_penalty' => $validated['afternoon_penalty'],
            'ramadan' => $ramadanFlag,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.schedules', ['type' => $type])->with('success', 'Horaire mis à jour avec succès');
    }

    public function deleteSchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        DB::table('schedules')->where('id', $id)->delete();
        return redirect()->route('admin.schedules')->with('success', 'Horaire supprimé avec succès');
    }

    // ==================== USER SCHEDULES ====================
    
    public function userSchedules(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;
        
        $userSchedules = DB::table('users_schedules')
            ->leftJoin('schedules', 'users_schedules.schedule_id', '=', 'schedules.id')
            ->select('users_schedules.*', 'schedules.name as schedule_name')
            ->where('users_schedules.ramadan', $ramadanFlag)
            ->orderBy('users_schedules.employee_code')
            ->get();
            
        return view('admin.user-schedules.index', compact('userSchedules'));
    }

    public function createUserSchedule(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;
        
        $schedules = DB::table('schedules')
            ->where('ramadan', $ramadanFlag)
            ->orderBy('name')
            ->get();
        
        // Fetch users from biostar2_ac database
        $users = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");
        
        return view('admin.user-schedules.create', compact('schedules', 'users', 'type'));
    }

    public function storeUserSchedule(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'employee_code' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'schedule_id' => 'nullable|exists:schedules,id',
        ]);

        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;

        // Check if combination of employee_code and ramadan already exists
        $exists = DB::table('users_schedules')
            ->where('employee_code', $validated['employee_code'])
            ->where('ramadan', $ramadanFlag)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'employee_code' => 'Cet employé a déjà un horaire ' . ($ramadanFlag == 1 ? 'Ramadan' : 'normal') . ' assigné.'
            ])->withInput();
        }

        DB::table('users_schedules')->insert([
            'employee_code' => $validated['employee_code'],
            'full_name' => $validated['full_name'],
            'schedule_id' => $validated['schedule_id'],
            'ramadan' => $ramadanFlag,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.user-schedules', ['type' => $type])->with('success', 'Horaire utilisateur créé avec succès');
    }

    public function editUserSchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $userSchedule = DB::table('users_schedules')->where('id', $id)->first();
        if (!$userSchedule) {
            return redirect()->route('admin.user-schedules')->with('error', 'Horaire utilisateur non trouvé');
        }
        
        $ramadanFlag = $userSchedule->ramadan ?? 0;
        $schedules = DB::table('schedules')
            ->where('ramadan', $ramadanFlag)
            ->orderBy('name')
            ->get();
            
        return view('admin.user-schedules.edit', compact('userSchedule', 'schedules'));
    }

    public function updateUserSchedule(Request $request, $id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'employee_code' => 'required|string|max:255',
            'full_name' => 'required|string|max:255',
            'schedule_id' => 'nullable|exists:schedules,id',
        ]);

        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;

        // Check if combination of employee_code and ramadan already exists (excluding current record)
        $exists = DB::table('users_schedules')
            ->where('employee_code', $validated['employee_code'])
            ->where('ramadan', $ramadanFlag)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'employee_code' => 'Cet employé a déjà un horaire ' . ($ramadanFlag == 1 ? 'Ramadan' : 'normal') . ' assigné.'
            ])->withInput();
        }

        DB::table('users_schedules')->where('id', $id)->update([
            'employee_code' => $validated['employee_code'],
            'full_name' => $validated['full_name'],
            'schedule_id' => $validated['schedule_id'],
            'ramadan' => $ramadanFlag,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.user-schedules', ['type' => $type])->with('success', 'Horaire utilisateur mis à jour avec succès');
    }

    public function deleteUserSchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        DB::table('users_schedules')->where('id', $id)->delete();
        return redirect()->route('admin.user-schedules')->with('success', 'Horaire utilisateur supprimé avec succès');
    }

    // ==================== SATURDAY SCHEDULES ====================
    
    public function saturdaySchedules()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $saturdaySchedules = DB::table('saturday_special_schedules')
            ->join('users_schedules', 'saturday_special_schedules.user_id', '=', 'users_schedules.id')
            ->join('schedules', 'saturday_special_schedules.schedule_id', '=', 'schedules.id')
            ->select(
                'saturday_special_schedules.*',
                'users_schedules.employee_code',
                'users_schedules.full_name',
                'schedules.name as schedule_name'
            )
            ->get();
            
        return view('admin.saturday-schedules.index', compact('saturdaySchedules'));
    }

    public function createSaturdaySchedule()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        // Fetch users from biostar2_ac database
        $users = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");
        
        $schedules = DB::table('schedules')->orderBy('name')->get();
        return view('admin.saturday-schedules.create', compact('users', 'schedules'));
    }

    public function storeSaturdaySchedule(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'user_id' => 'required|string',
            'schedule_id' => 'required|exists:schedules,id',
        ]);

        // Get user info from biostar database
        $biostarUser = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            WHERE u.usrid = ?
            LIMIT 1
        ", [$validated['user_id']]);

        if (empty($biostarUser)) {
            return redirect()->back()->withErrors(['user_id' => 'Employé non trouvé'])->withInput();
        }

        $biostarUser = $biostarUser[0];

        // Check or create user in users_schedules table
        $userSchedule = DB::table('users_schedules')
            ->where('employee_code', $validated['user_id'])
            ->first();

        if (!$userSchedule) {
            // Create new user schedule entry
            $userScheduleId = DB::table('users_schedules')->insertGetId([
                'employee_code' => $biostarUser->employee_code,
                'full_name' => $biostarUser->full_name,
                'schedule_id' => null, // No default schedule
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userScheduleId = $userSchedule->id;
        }

        // Check if this user already has a Saturday schedule
        $exists = DB::table('saturday_special_schedules')
            ->where('user_id', $userScheduleId)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['user_id' => 'Cet employé a déjà un horaire samedi'])->withInput();
        }

        DB::table('saturday_special_schedules')->insert([
            'user_id' => $userScheduleId,
            'schedule_id' => $validated['schedule_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.saturday-schedules')->with('success', 'Horaire samedi créé avec succès');
    }

    public function editSaturdaySchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $saturdaySchedule = DB::table('saturday_special_schedules')->where('id', $id)->first();
        if (!$saturdaySchedule) {
            return redirect()->route('admin.saturday-schedules')->with('error', 'Horaire samedi non trouvé');
        }
        
        // Fetch users from biostar2_ac database
        $users = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");
        
        $schedules = DB::table('schedules')->orderBy('name')->get();
        return view('admin.saturday-schedules.edit', compact('saturdaySchedule', 'users', 'schedules'));
    }

    public function updateSaturdaySchedule(Request $request, $id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'user_id' => 'required|string',
            'schedule_id' => 'required|exists:schedules,id',
        ]);

        // Get user info from biostar database
        $biostarUser = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            WHERE u.usrid = ?
            LIMIT 1
        ", [$validated['user_id']]);

        if (empty($biostarUser)) {
            return redirect()->back()->withErrors(['user_id' => 'Employé non trouvé'])->withInput();
        }

        $biostarUser = $biostarUser[0];

        // Check or create user in users_schedules table
        $userSchedule = DB::table('users_schedules')
            ->where('employee_code', $validated['user_id'])
            ->first();

        if (!$userSchedule) {
            // Create new user schedule entry
            $userScheduleId = DB::table('users_schedules')->insertGetId([
                'employee_code' => $biostarUser->employee_code,
                'full_name' => $biostarUser->full_name,
                'schedule_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userScheduleId = $userSchedule->id;
        }

        // Check if another record already has this user (excluding current record)
        $exists = DB::table('saturday_special_schedules')
            ->where('user_id', $userScheduleId)
            ->where('id', '!=', $id)
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['user_id' => 'Cet employé a déjà un horaire samedi'])->withInput();
        }

        DB::table('saturday_special_schedules')->where('id', $id)->update([
            'user_id' => $userScheduleId,
            'schedule_id' => $validated['schedule_id'],
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.saturday-schedules')->with('success', 'Horaire samedi mis à jour avec succès');
    }

    public function deleteSaturdaySchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        DB::table('saturday_special_schedules')->where('id', $id)->delete();
        return redirect()->route('admin.saturday-schedules')->with('success', 'Horaire samedi supprimé avec succès');
    }

    // ==================== USER WORKDAYS ====================
    
    public function userWorkdays()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $userWorkdays = DB::table('user_workdays')
            ->join('users_schedules', 'user_workdays.user_id', '=', 'users_schedules.id')
            ->select(
                'users_schedules.id as user_id',
                'users_schedules.employee_code',
                'users_schedules.full_name',
                DB::raw('GROUP_CONCAT(user_workdays.weekday ORDER BY user_workdays.weekday) as workdays')
            )
            ->groupBy('users_schedules.id', 'users_schedules.employee_code', 'users_schedules.full_name')
            ->get();
            
        return view('admin.user-workdays.index', compact('userWorkdays'));
    }

    public function createUserWorkday()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $biostarUsers = DB::connection('biostar_tna')->select(" 
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");

        $users = collect($biostarUsers)
            ->map(function ($user) {
                return (object) [
                    'employee_code' => $this->normalizeEmployeeCode($user->employee_code),
                    'full_name' => trim((string) $user->full_name),
                ];
            })
            ->filter(function ($user) {
                return $user->employee_code !== '';
            })
            ->sortBy('full_name')
            ->values();
        
        $weekdays = [
            0 => 'Lundi',
            1 => 'Mardi',
            2 => 'Mercredi',
            3 => 'Jeudi',
            4 => 'Vendredi',
            5 => 'Samedi',
            6 => 'Dimanche'
        ];
        return view('admin.user-workdays.create', compact('users', 'weekdays'));
    }

    public function storeUserWorkday(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'employee_code' => 'required|string',
            'full_name' => 'nullable|string|max:255',
            'workdays' => 'required|array|min:1',
            'workdays.*' => 'integer|between:0,6',
        ]);

        $employeeCode = $this->normalizeEmployeeCode($validated['employee_code']);
        $fullName = trim((string) ($validated['full_name'] ?? ''));

        $userRecord = DB::table('users_schedules')->where('employee_code', $employeeCode)->first();
        if (!$userRecord) {
            $userId = DB::table('users_schedules')->insertGetId([
                'employee_code' => $employeeCode,
                'full_name' => $fullName !== '' ? $fullName : $employeeCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $userId = $userRecord->id;
        }

        DB::table('user_workdays')->where('user_id', $userId)->delete();

        foreach ($validated['workdays'] as $weekday) {
            DB::table('user_workdays')->insert([
                'user_id' => $userId,
                'weekday' => $weekday,
            ]);
        }

        return redirect()->route('admin.user-workdays')->with('success', 'Jours de travail créés avec succès');
    }

    public function editUserWorkday($userId)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $user = DB::table('users_schedules')->where('id', $userId)->first();
        if (!$user) {
            return redirect()->route('admin.user-workdays')->with('error', 'Utilisateur non trouvé');
        }
        
        $existingWorkdays = DB::table('user_workdays')
            ->where('user_id', $userId)
            ->pluck('weekday')
            ->toArray();
            
        $weekdays = [
            0 => 'Lundi',
            1 => 'Mardi',
            2 => 'Mercredi',
            3 => 'Jeudi',
            4 => 'Vendredi',
            5 => 'Samedi',
            6 => 'Dimanche'
        ];
        
        return view('admin.user-workdays.edit', compact('user', 'existingWorkdays', 'weekdays'));
    }

    public function updateUserWorkday(Request $request, $userId)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'workdays' => 'required|array|min:1',
            'workdays.*' => 'integer|between:0,6',
        ]);

        DB::table('user_workdays')->where('user_id', $userId)->delete();

        foreach ($validated['workdays'] as $weekday) {
            DB::table('user_workdays')->insert([
                'user_id' => $userId,
                'weekday' => $weekday,
            ]);
        }

        return redirect()->route('admin.user-workdays')->with('success', 'Jours de travail mis à jour avec succès');
    }

    public function deleteUserWorkday($userId)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        DB::table('user_workdays')->where('user_id', $userId)->delete();
        return redirect()->route('admin.user-workdays')->with('success', 'Jours de travail supprimés avec succès');
    }

    // ==================== PUBLIC HOLIDAYS ====================
    
    public function publicHolidays()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $holidays = DB::table('public_holidays')->orderBy('holiday_date', 'desc')->get();
        return view('admin.public-holidays.index', compact('holidays'));
    }

    public function createPublicHoliday()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        return view('admin.public-holidays.create');
    }

    public function storePublicHoliday(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'holiday_date' => 'required|date|unique:public_holidays,holiday_date',
            'holiday_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::table('public_holidays')->insert([
            'holiday_date' => $validated['holiday_date'],
            'holiday_name' => $validated['holiday_name'],
            'description' => $validated['description'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.public-holidays')->with('success', 'Jour férié créé avec succès');
    }

    public function editPublicHoliday($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $holiday = DB::table('public_holidays')->where('id', $id)->first();
        if (!$holiday) {
            return redirect()->route('admin.public-holidays')->with('error', 'Jour férié non trouvé');
        }
        
        return view('admin.public-holidays.edit', compact('holiday'));
    }

    public function updatePublicHoliday(Request $request, $id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'holiday_date' => 'required|date|unique:public_holidays,holiday_date,' . $id,
            'holiday_name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::table('public_holidays')->where('id', $id)->update([
            'holiday_date' => $validated['holiday_date'],
            'holiday_name' => $validated['holiday_name'],
            'description' => $validated['description'],
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.public-holidays')->with('success', 'Jour férié mis à jour avec succès');
    }

    public function deletePublicHoliday($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        DB::table('public_holidays')->where('id', $id)->delete();
        return redirect()->route('admin.public-holidays')->with('success', 'Jour férié supprimé avec succès');
    }

    // ==================== RAMADAN PERIODS ====================
    
    public function ramadanPeriods()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $periods = DB::table('ramadan')->orderBy('ramadan_start', 'desc')->get();
        return view('admin.ramadan-periods.index', compact('periods'));
    }

    public function createRamadanPeriod()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        return view('admin.ramadan-periods.create');
    }

    public function storeRamadanPeriod(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $request->validate([
            'ramadan_start' => 'required|date',
            'ramadan_end' => 'required|date|after:ramadan_start',
        ]);

        DB::table('ramadan')->insert([
            'ramadan_start' => $request->ramadan_start,
            'ramadan_end' => $request->ramadan_end,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.ramadan-periods')->with('success', 'Période de Ramadan créée avec succès');
    }

    public function editRamadanPeriod($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $period = DB::table('ramadan')->where('id', $id)->first();
        if (!$period) {
            return redirect()->route('admin.ramadan-periods')->with('error', 'Période non trouvée');
        }

        return view('admin.ramadan-periods.edit', compact('period'));
    }

    public function updateRamadanPeriod(Request $request, $id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $request->validate([
            'ramadan_start' => 'required|date',
            'ramadan_end' => 'required|date|after:ramadan_start',
        ]);

        DB::table('ramadan')->where('id', $id)->update([
            'ramadan_start' => $request->ramadan_start,
            'ramadan_end' => $request->ramadan_end,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.ramadan-periods')->with('success', 'Période de Ramadan mise à jour avec succès');
    }

    public function deleteRamadanPeriod($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        DB::table('ramadan')->where('id', $id)->delete();
        return redirect()->route('admin.ramadan-periods')->with('success', 'Période de Ramadan supprimée avec succès');
    }

    // ==================== USER DAY SCHEDULES ====================

    public function userDaySchedules(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $type = $request->input('type', 'normal');
        $ramadanFlag = $type === 'ramadan' ? 1 : 0;

        $daySchedules = DB::table('user_day_schedules as uds')
            ->join('schedules as s', 'uds.schedule_id', '=', 's.id')
            ->select('uds.id', 'uds.employee_code', 'uds.full_name', 'uds.weekday', 's.name as schedule_name', 'uds.ramadan')
            ->where('uds.ramadan', $ramadanFlag)
            ->orderBy('uds.employee_code')
            ->orderBy('uds.weekday')
            ->get();

        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

        return view('admin.user-day-schedules.index', compact('daySchedules', 'days', 'type'));
    }

    public function createUserDaySchedule(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $type = $request->input('type', 'normal');

        $schedules = DB::table('schedules')->orderBy('name')->get();
        $users = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

        return view('admin.user-day-schedules.create', compact('schedules', 'users', 'days', 'type'));
    }

    public function storeUserDaySchedule(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'employee_code' => 'required|string',
            'full_name'     => 'required|string|max:255',
            'weekday'       => 'required|integer|min:0|max:6',
            'schedule_id'   => 'required|exists:schedules,id',
            'type'          => 'nullable|string',
        ]);

        $ramadanFlag = ($request->input('type') === 'ramadan') ? 1 : 0;

        // Upsert: replace if same employee+day+ramadan already exists
        $existing = DB::table('user_day_schedules')
            ->where('employee_code', $validated['employee_code'])
            ->where('weekday', $validated['weekday'])
            ->where('ramadan', $ramadanFlag)
            ->first();

        if ($existing) {
            DB::table('user_day_schedules')->where('id', $existing->id)->update([
                'full_name'   => $validated['full_name'],
                'schedule_id' => $validated['schedule_id'],
                'updated_at'  => now(),
            ]);
        } else {
            DB::table('user_day_schedules')->insert([
                'employee_code' => $validated['employee_code'],
                'full_name'     => $validated['full_name'],
                'weekday'       => $validated['weekday'],
                'schedule_id'   => $validated['schedule_id'],
                'ramadan'       => $ramadanFlag,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        return redirect()->route('admin.user-day-schedules', ['type' => $request->input('type', 'normal')])
            ->with('success', 'Horaire journalier enregistré avec succès');
    }

    public function editUserDaySchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $daySchedule = DB::table('user_day_schedules as uds')
            ->join('schedules as s', 'uds.schedule_id', '=', 's.id')
            ->select('uds.*', 's.name as schedule_name')
            ->where('uds.id', $id)
            ->first();

        if (!$daySchedule) {
            return redirect()->route('admin.user-day-schedules')->with('error', 'Entrée non trouvée');
        }

        $ramadanFlag = $daySchedule->ramadan;
        $schedules   = DB::table('schedules')->orderBy('name')->get();
        $users = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");
        $days        = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $type        = $ramadanFlag ? 'ramadan' : 'normal';

        return view('admin.user-day-schedules.edit', compact('daySchedule', 'schedules', 'users', 'days', 'type'));
    }

    public function updateUserDaySchedule(Request $request, $id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'employee_code' => 'required|string',
            'full_name'     => 'required|string|max:255',
            'weekday'       => 'required|integer|min:0|max:6',
            'schedule_id'   => 'required|exists:schedules,id',
        ]);

        $ramadanFlag = DB::table('user_day_schedules')->where('id', $id)->value('ramadan');

        DB::table('user_day_schedules')->where('id', $id)->update([
            'employee_code' => $validated['employee_code'],
            'full_name'     => $validated['full_name'],
            'weekday'       => $validated['weekday'],
            'schedule_id'   => $validated['schedule_id'],
            'updated_at'    => now(),
        ]);

        $type = $ramadanFlag ? 'ramadan' : 'normal';
        return redirect()->route('admin.user-day-schedules', ['type' => $type])
            ->with('success', 'Horaire journalier mis à jour avec succès');
    }

    public function deleteUserDaySchedule($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $ramadan = DB::table('user_day_schedules')->where('id', $id)->value('ramadan');
        DB::table('user_day_schedules')->where('id', $id)->delete();

        $type = $ramadan ? 'ramadan' : 'normal';
        return redirect()->route('admin.user-day-schedules', ['type' => $type])
            ->with('success', 'Horaire journalier supprimé avec succès');
    }

    // ==================== USER SCHEDULE PERIODS (date-range overrides) ====================

    public function userSchedulePeriods()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $periods = DB::table('user_schedule_periods as usp')
            ->join('schedules as s', 'usp.schedule_id', '=', 's.id')
            ->select('usp.*', 's.name as schedule_name')
            ->orderBy('usp.start_date', 'desc')
            ->get();

        return view('admin.user-schedule-periods.index', compact('periods'));
    }

    public function createUserSchedulePeriod()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $schedules = DB::table('schedules')->orderBy('name')->get();
        $users = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");

        return view('admin.user-schedule-periods.create', compact('schedules', 'users'));
    }

    public function storeUserSchedulePeriod(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'employee_code' => 'required|string|max:255',
            'full_name'     => 'required|string|max:255',
            'schedule_id'   => 'required|exists:schedules,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        $overlap = DB::table('user_schedule_periods')
            ->where('employee_code', $validated['employee_code'])
            ->where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->exists();

        if ($overlap) {
            return back()->withErrors([
                'start_date' => 'Cet employé a déjà une période qui chevauche ces dates.'
            ])->withInput();
        }

        DB::table('user_schedule_periods')->insert([
            'employee_code' => $validated['employee_code'],
            'full_name'     => $validated['full_name'],
            'schedule_id'   => $validated['schedule_id'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return redirect()->route('admin.user-schedule-periods')->with('success', 'Période créée avec succès');
    }

    public function editUserSchedulePeriod($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $period = DB::table('user_schedule_periods')->where('id', $id)->first();
        if (!$period) {
            return redirect()->route('admin.user-schedule-periods')->with('error', 'Période non trouvée');
        }

        $schedules = DB::table('schedules')->orderBy('name')->get();
        $users = DB::connection('biostar_tna')->select("
            SELECT u.NM as full_name, u.usrid as employee_code
            FROM biostar2_ac.t_usr u
            JOIN biostar2_ac.t_usrgr g ON u.usrgruid = g.usrgruid
            WHERE g.usrgruid IN (
                SELECT usrgruid
                FROM biostar2_ac.t_usrgr
                WHERE parentusrgruid = 1002
            )
            ORDER BY u.NM
        ");

        return view('admin.user-schedule-periods.edit', compact('period', 'schedules', 'users'));
    }

    public function updateUserSchedulePeriod(Request $request, $id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'employee_code' => 'required|string|max:255',
            'full_name'     => 'required|string|max:255',
            'schedule_id'   => 'required|exists:schedules,id',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
        ]);

        $overlap = DB::table('user_schedule_periods')
            ->where('employee_code', $validated['employee_code'])
            ->where('start_date', '<=', $validated['end_date'])
            ->where('end_date', '>=', $validated['start_date'])
            ->where('id', '!=', $id)
            ->exists();

        if ($overlap) {
            return back()->withErrors([
                'start_date' => 'Cet employé a déjà une période qui chevauche ces dates.'
            ])->withInput();
        }

        DB::table('user_schedule_periods')->where('id', $id)->update([
            'employee_code' => $validated['employee_code'],
            'full_name'     => $validated['full_name'],
            'schedule_id'   => $validated['schedule_id'],
            'start_date'    => $validated['start_date'],
            'end_date'      => $validated['end_date'],
            'updated_at'    => now(),
        ]);

        return redirect()->route('admin.user-schedule-periods')->with('success', 'Période mise à jour avec succès');
    }

    public function deleteUserSchedulePeriod($id)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        DB::table('user_schedule_periods')->where('id', $id)->delete();
        return redirect()->route('admin.user-schedule-periods')->with('success', 'Période supprimée avec succès');
    }

    // ==================== ROLES & DROITS ====================

    public function roles()
    {
        if ($redirect = $this->checkSuperAdmin()) return $redirect;

        $users = DB::table('users')
            ->select('id', 'name', 'email', 'role', 'droit', 'updated_at')
            ->orderBy('name')
            ->get();

        return view('admin.roles.index', compact('users'));
    }

    public function updateRole(Request $request, $id)
    {
        if ($redirect = $this->checkSuperAdmin()) return $redirect;

        $currentUser = Session::get('user');
        if ((int) $currentUser->id === (int) $id) {
            return redirect()->route('admin.roles')->with('error', 'Vous ne pouvez pas modifier vos propres droits depuis cet ecran.');
        }

        $validated = $request->validate([
            'role' => 'required|in:user,admin',
            'droit' => 'required|integer|in:0,1,2,3',
        ]);

        $targetUser = DB::table('users')->where('id', $id)->first();
        if (!$targetUser) {
            return redirect()->route('admin.roles')->with('error', 'Utilisateur introuvable.');
        }

        DB::table('users')->where('id', $id)->update([
            'role' => $validated['role'],
            'droit' => $validated['droit'],
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.roles')->with([
            'success' => 'Role et droit mis a jour avec succes.',
            'undo_role_change' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'role' => $targetUser->role ?? 'user',
                'droit' => $targetUser->droit ?? 1,
            ],
        ]);
    }

    public function undoRole(Request $request)
    {
        if ($redirect = $this->checkSuperAdmin()) return $redirect;

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|in:user,admin',
            'droit' => 'required|integer|in:0,1,2,3',
        ]);

        $currentUser = Session::get('user');
        if ((int) $currentUser->id === (int) $validated['user_id']) {
            return redirect()->route('admin.roles')->with('error', 'Vous ne pouvez pas modifier vos propres droits depuis cet ecran.');
        }

        DB::table('users')->where('id', $validated['user_id'])->update([
            'role' => $validated['role'],
            'droit' => $validated['droit'],
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.roles')->with('success', 'Modification annulee.');
    }

    // ==================== DASHBOARD ====================
    
    public function dashboard()
    {
        if ($redirect = $this->checkAdmin()) return $redirect;
        
        $stats = [
            'schedules'        => DB::table('schedules')->count(),
            'user_schedules'   => DB::table('users_schedules')->count(),
            'saturday_schedules' => DB::table('saturday_special_schedules')->count(),
            'public_holidays'  => DB::table('public_holidays')->count(),
            'ramadan_periods'  => DB::table('ramadan')->count(),
            'day_schedules'    => DB::table('user_day_schedules')->count(),
            'schedule_periods' => DB::table('user_schedule_periods')->count(),
            'users'            => DB::table('users')->count(),
        ];
        
        return view('admin.dashboard', compact('stats'));
    }
}
