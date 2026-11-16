<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class AnomalieNotificationController extends Controller
{
    private function checkAdmin()
    {
        $user = Session::get('user');
        if (!$user || $user->role !== 'admin') {
            return redirect()->route('login')->with('error', 'Accès non autorisé');
        }
        return null;
    }

    public function index(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $settings = DB::table('anomalie_mail_settings')->first();

        $dates = DB::table('anomalie_notifications')
            ->select('work_date')
            ->distinct()
            ->orderBy('work_date', 'desc')
            ->pluck('work_date');

        $showLast7 = $request->boolean('last7');

        if ($showLast7) {
            $selectedDate = null;
            $rows = DB::table('anomalie_notifications')
                ->where('work_date', '>=', now()->subDays(7)->toDateString())
                ->orderBy('work_date', 'desc')
                ->orderBy('nom')
                ->get();
        } else {
            $selectedDate = $request->input('date') ?: $dates->first();
            $rows = $selectedDate
                ? DB::table('anomalie_notifications')
                    ->where('work_date', $selectedDate)
                    ->orderBy('nom')
                    ->get()
                : collect();
        }

        return view('admin.anomalie-notifications.index', [
            'enabled'      => (bool) ($settings->enabled ?? true),
            'dates'        => $dates,
            'selectedDate' => $selectedDate,
            'rows'         => $rows,
            'showLast7'    => $showLast7,
        ]);
    }

    public function buildNow(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        // No --date given: anomalie:build computes the previous business day
        // itself (Monday -> last Friday, otherwise -> yesterday).
        Artisan::call('anomalie:build');
        $output = trim(Artisan::output());

        // Pull the work_date the command just built so we can jump straight to it.
        $builtDate = DB::table('anomalie_notifications')->max('work_date');

        return redirect()->route('admin.anomalie-notifications', ['date' => $builtDate])
            ->with('success', "Liste construite. {$output}");
    }

    public function toggleSetting(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $settings = DB::table('anomalie_mail_settings')->first();
        $newValue = !($settings->enabled ?? true);

        DB::table('anomalie_mail_settings')->where('id', $settings->id)->update([
            'enabled'    => $newValue,
            'updated_at' => now(),
        ]);

        return redirect()->route('admin.anomalie-notifications')
            ->with('success', $newValue ? 'Envoi automatique activé.' : 'Envoi automatique désactivé — plus rien ne sera envoyé.');
    }

    public function updateSelection(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'work_date' => 'required|date',
            'selected'  => 'nullable|array',
            'selected.*' => 'integer',
        ]);

        $this->applySelection($validated['work_date'], $validated['selected'] ?? []);

        return redirect()->route('admin.anomalie-notifications', ['date' => $validated['work_date']])
            ->with('success', 'Sélection enregistrée.');
    }

    public function sendNow(Request $request)
    {
        if ($redirect = $this->checkAdmin()) return $redirect;

        $validated = $request->validate([
            'work_date' => 'required|date',
            'selected'  => 'nullable|array',
            'selected.*' => 'integer',
        ]);

        // Apply the checkbox selection first, then send immediately for that
        // date instead of waiting for the 09:00 scheduled run.
        $this->applySelection($validated['work_date'], $validated['selected'] ?? []);

        Artisan::call('anomalie:send', ['--date' => $validated['work_date']]);
        $output = trim(Artisan::output());

        return redirect()->route('admin.anomalie-notifications', ['date' => $validated['work_date']])
            ->with('success', "Envoi terminé. {$output}");
    }

    /**
     * Checked = included (excluded=false), unchecked = excluded=true.
     * Only rows still pending are touched — ones already sent/failed keep
     * their historical state regardless of the current checkbox state.
     */
    private function applySelection(string $workDate, array $selectedIds): void
    {
        DB::table('anomalie_notifications')
            ->where('work_date', $workDate)
            ->where('status', 'pending')
            ->whereIn('id', $selectedIds)
            ->update(['excluded' => false, 'updated_at' => now()]);

        DB::table('anomalie_notifications')
            ->where('work_date', $workDate)
            ->where('status', 'pending')
            ->whereNotIn('id', $selectedIds)
            ->update(['excluded' => true, 'updated_at' => now()]);
    }
}
