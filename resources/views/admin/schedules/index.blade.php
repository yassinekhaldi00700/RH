@include('header')

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Gestion des Horaires</h1>
        <a href="{{ route('admin.schedules.create', ['type' => request('type', 'normal')]) }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nouveau Horaire</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Filter Tabs -->
    <div class="mb-3">
        <a href="{{ route('admin.schedules', ['type' => 'normal']) }}" 
           class="btn {{ request('type', 'normal') == 'normal' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-clock"></i> Liste des Horaires
        </a>
        <a href="{{ route('admin.schedules', ['type' => 'ramadan']) }}" 
           class="btn {{ request('type') == 'ramadan' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-moon"></i> Horaires Ramadan
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                {{ request('type') == 'ramadan' ? 'Horaires Ramadan' : 'Liste des Horaires' }}
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Début Matin</th>
                            <th>Pause Déjeuner</th>
                            <th>Fin Déjeuner</th>
                            <th>Fin Journée</th>
                            <th>Pénalités (M/D/A)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($schedules as $schedule)
                        <tr>
                            <td><strong>{{ $schedule->name }}</strong></td>
                            <td>{{ substr($schedule->morning_start, 0, 5) }}</td>
                            <td>{{ substr($schedule->lunch_start, 0, 5) }}</td>
                            <td>{{ substr($schedule->lunch_end, 0, 5) }}</td>
                            <td>{{ substr($schedule->workday_end, 0, 5) }}</td>
                            <td>{{ $schedule->morning_penalty }}/{{ $schedule->lunch_penalty }}/{{ $schedule->afternoon_penalty }}</td>
                            <td>
                                <a href="{{ route('admin.schedules.edit', $schedule->id) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('admin.schedules.delete', $schedule->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center">Aucun horaire trouvé</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('footer')
