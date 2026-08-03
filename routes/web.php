<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ScheduleManagementController;



/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::post('/filter-dates', [AttendanceController::class, 'getAttendanceData'])->name('date.filter');
Route::get('/attendance-data', [AttendanceController::class, 'getAttendanceData']);
Route::get('/export-excel', [AttendanceController::class, 'exportExcel'])->name('export.excel');
Route::get('/download-cumulative', [AttendanceController::class, 'downloadCumulativeData'])->name('download.cumulative');
Route::get('/download-cumul-personnel', [AttendanceController::class, 'downloadCumulPersonnel'])->name('download.cumul.personnel');
Route::get('/api/groups', [AttendanceController::class, 'getGroups'])->name('api.groups');
Route::get('/api/persons', [AttendanceController::class, 'getPersons'])->name('api.persons');

Route::get('/login', [AttendanceController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AttendanceController::class, 'login']);
Route::get('/logout', [AttendanceController::class, 'logout'])->name('logout');

Route::get('/register', [AttendanceController::class, 'showRegisterForm'])->name('register');
Route::post('/register', [AttendanceController::class, 'register']);

Route::middleware(['checklogin'])->group(function () {
  
    Route::get('/', [AttendanceController::class, 'add_session'])->name('add_session');
    Route::get('/change-password', [AttendanceController::class, 'showChangePasswordForm'])->name('change.password');
    Route::post('/change-password', [AttendanceController::class, 'changePassword'])->name('change.password.update');
    
    // Admin Routes (protected by checklogin middleware)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [ScheduleManagementController::class, 'dashboard'])->name('dashboard');
        
        // Schedules
        Route::get('/schedules', [ScheduleManagementController::class, 'schedules'])->name('schedules');
        Route::get('/schedules/create', [ScheduleManagementController::class, 'createSchedule'])->name('schedules.create');
        Route::post('/schedules', [ScheduleManagementController::class, 'storeSchedule'])->name('schedules.store');
        Route::get('/schedules/{id}/edit', [ScheduleManagementController::class, 'editSchedule'])->name('schedules.edit');
        Route::put('/schedules/{id}', [ScheduleManagementController::class, 'updateSchedule'])->name('schedules.update');
        Route::delete('/schedules/{id}', [ScheduleManagementController::class, 'deleteSchedule'])->name('schedules.delete');
        
        // User Schedules
        Route::get('/user-schedules', [ScheduleManagementController::class, 'userSchedules'])->name('user-schedules');
        Route::get('/user-schedules/create', [ScheduleManagementController::class, 'createUserSchedule'])->name('user-schedules.create');
        Route::post('/user-schedules', [ScheduleManagementController::class, 'storeUserSchedule'])->name('user-schedules.store');
        Route::get('/user-schedules/{id}/edit', [ScheduleManagementController::class, 'editUserSchedule'])->name('user-schedules.edit');
        Route::put('/user-schedules/{id}', [ScheduleManagementController::class, 'updateUserSchedule'])->name('user-schedules.update');
        Route::delete('/user-schedules/{id}', [ScheduleManagementController::class, 'deleteUserSchedule'])->name('user-schedules.delete');
        
        // Saturday Schedules
        Route::get('/saturday-schedules', [ScheduleManagementController::class, 'saturdaySchedules'])->name('saturday-schedules');
        Route::get('/saturday-schedules/create', [ScheduleManagementController::class, 'createSaturdaySchedule'])->name('saturday-schedules.create');
        Route::post('/saturday-schedules', [ScheduleManagementController::class, 'storeSaturdaySchedule'])->name('saturday-schedules.store');
        Route::get('/saturday-schedules/{id}/edit', [ScheduleManagementController::class, 'editSaturdaySchedule'])->name('saturday-schedules.edit');
        Route::put('/saturday-schedules/{id}', [ScheduleManagementController::class, 'updateSaturdaySchedule'])->name('saturday-schedules.update');
        Route::delete('/saturday-schedules/{id}', [ScheduleManagementController::class, 'deleteSaturdaySchedule'])->name('saturday-schedules.delete');
        
        // User Workdays
        Route::get('/user-workdays', [ScheduleManagementController::class, 'userWorkdays'])->name('user-workdays');
        Route::get('/user-workdays/create', [ScheduleManagementController::class, 'createUserWorkday'])->name('user-workdays.create');
        Route::post('/user-workdays', [ScheduleManagementController::class, 'storeUserWorkday'])->name('user-workdays.store');
        Route::get('/user-workdays/{userId}/edit', [ScheduleManagementController::class, 'editUserWorkday'])->name('user-workdays.edit');
        Route::put('/user-workdays/{userId}', [ScheduleManagementController::class, 'updateUserWorkday'])->name('user-workdays.update');
        Route::delete('/user-workdays/{userId}', [ScheduleManagementController::class, 'deleteUserWorkday'])->name('user-workdays.delete');
        
        // Public Holidays
        Route::get('/public-holidays', [ScheduleManagementController::class, 'publicHolidays'])->name('public-holidays');
        Route::get('/public-holidays/create', [ScheduleManagementController::class, 'createPublicHoliday'])->name('public-holidays.create');
        Route::post('/public-holidays', [ScheduleManagementController::class, 'storePublicHoliday'])->name('public-holidays.store');
        Route::get('/public-holidays/{id}/edit', [ScheduleManagementController::class, 'editPublicHoliday'])->name('public-holidays.edit');
        Route::put('/public-holidays/{id}', [ScheduleManagementController::class, 'updatePublicHoliday'])->name('public-holidays.update');
        Route::delete('/public-holidays/{id}', [ScheduleManagementController::class, 'deletePublicHoliday'])->name('public-holidays.delete');
        
        // User Day Schedules (per-weekday overrides)
        Route::get('/user-day-schedules', [ScheduleManagementController::class, 'userDaySchedules'])->name('user-day-schedules');
        Route::get('/user-day-schedules/create', [ScheduleManagementController::class, 'createUserDaySchedule'])->name('user-day-schedules.create');
        Route::post('/user-day-schedules', [ScheduleManagementController::class, 'storeUserDaySchedule'])->name('user-day-schedules.store');
        Route::get('/user-day-schedules/{id}/edit', [ScheduleManagementController::class, 'editUserDaySchedule'])->name('user-day-schedules.edit');
        Route::put('/user-day-schedules/{id}', [ScheduleManagementController::class, 'updateUserDaySchedule'])->name('user-day-schedules.update');
        Route::delete('/user-day-schedules/{id}', [ScheduleManagementController::class, 'deleteUserDaySchedule'])->name('user-day-schedules.delete');

        // Ramadan Periods
        Route::get('/ramadan-periods', [ScheduleManagementController::class, 'ramadanPeriods'])->name('ramadan-periods');
        Route::get('/ramadan-periods/create', [ScheduleManagementController::class, 'createRamadanPeriod'])->name('ramadan-periods.create');
        Route::post('/ramadan-periods', [ScheduleManagementController::class, 'storeRamadanPeriod'])->name('ramadan-periods.store');
        Route::get('/ramadan-periods/{id}/edit', [ScheduleManagementController::class, 'editRamadanPeriod'])->name('ramadan-periods.edit');
        Route::put('/ramadan-periods/{id}', [ScheduleManagementController::class, 'updateRamadanPeriod'])->name('ramadan-periods.update');
        Route::delete('/ramadan-periods/{id}', [ScheduleManagementController::class, 'deleteRamadanPeriod'])->name('ramadan-periods.delete');

        // Profile AC/AD
        Route::get('/profile-acad', [AttendanceController::class, 'profileAcAd'])->name('profile-acad');
        Route::post('/profile-acad', [AttendanceController::class, 'storeProfileAcAd'])->name('profile-acad.store');
        Route::put('/profile-acad/{id}', [AttendanceController::class, 'updateProfileAcAd'])->name('profile-acad.update');
        Route::delete('/profile-acad/{id}', [AttendanceController::class, 'destroyProfileAcAd'])->name('profile-acad.destroy');

        // Roles & droits (super admin: role admin + droit 0)
        Route::get('/roles', [ScheduleManagementController::class, 'roles'])->name('roles');
        Route::put('/roles/{id}', [ScheduleManagementController::class, 'updateRole'])->name('roles.update');
        Route::post('/roles/undo', [ScheduleManagementController::class, 'undoRole'])->name('roles.undo');
    });
    
});
