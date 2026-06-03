@include('header')

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Horaires du Samedi</h1>
        <a href="{{ route('admin.saturday-schedules.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvel Horaire Samedi</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Liste des Horaires Spéciaux du Samedi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Code Employé</th>
                            <th>Nom</th>
                            <th>Horaire Samedi</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($saturdaySchedules as $satSchedule)
                        <tr>
                            <td>{{ $satSchedule->employee_code }}</td>
                            <td>{{ $satSchedule->full_name }}</td>
                            <td><strong>{{ $satSchedule->schedule_name }}</strong></td>
                            <td>
                                <a href="{{ route('admin.saturday-schedules.edit', $satSchedule->id) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('admin.saturday-schedules.delete', $satSchedule->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">Aucun horaire samedi trouvé</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('footer')
