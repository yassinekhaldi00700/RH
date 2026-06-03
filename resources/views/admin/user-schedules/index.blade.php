@include('header')

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Horaires des Employés</h1>
        <a href="{{ route('admin.user-schedules.create', ['type' => request('type', 'normal')]) }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvel Employé</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Filter Tabs -->
    <div class="mb-3">
        <a href="{{ route('admin.user-schedules', ['type' => 'normal']) }}" 
           class="btn {{ request('type', 'normal') == 'normal' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-clock"></i> Horaires Normaux
        </a>
        <a href="{{ route('admin.user-schedules', ['type' => 'ramadan']) }}" 
           class="btn {{ request('type') == 'ramadan' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-moon"></i> Horaires Ramadan
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                {{ request('type') == 'ramadan' ? 'Employés - Horaires Ramadan' : 'Liste des Employés' }}
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Code Employé</th>
                            <th>Nom</th>
                            <th>Horaire</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($userSchedules as $userSchedule)
                        <tr>
                            <td>{{ $userSchedule->employee_code }}</td>
                            <td>{{ $userSchedule->full_name }}</td>
                            <td>{{ $userSchedule->schedule_name ?? 'Standard' }}</td>
                            <td>
                                <a href="{{ route('admin.user-schedules.edit', $userSchedule->id) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('admin.user-schedules.delete', $userSchedule->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">Aucun employé trouvé</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('footer')
