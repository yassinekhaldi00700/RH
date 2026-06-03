@include('header')

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Périodes de Ramadan</h1>
        <a href="{{ route('admin.ramadan-periods.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvelle Période</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Liste des Périodes de Ramadan</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Année</th>
                            <th>Date de Début</th>
                            <th>Date de Fin</th>
                            <th>Durée (jours)</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($periods as $period)
                        @php
                            $start = \Carbon\Carbon::parse($period->ramadan_start);
                            $end = \Carbon\Carbon::parse($period->ramadan_end);
                            $now = \Carbon\Carbon::now();
                            $duration = $start->diffInDays($end) + 1;
                            
                            if ($now->between($start, $end)) {
                                $status = '<span class="badge badge-success">En cours</span>';
                            } elseif ($now->lt($start)) {
                                $status = '<span class="badge badge-info">À venir</span>';
                            } else {
                                $status = '<span class="badge badge-secondary">Terminé</span>';
                            }
                        @endphp
                        <tr>
                            <td><strong>{{ $start->year }}</strong></td>
                            <td>{{ $start->format('d/m/Y') }}</td>
                            <td>{{ $end->format('d/m/Y') }}</td>
                            <td>{{ $duration }} jours</td>
                            <td>{!! $status !!}</td>
                            <td>
                                <a href="{{ route('admin.ramadan-periods.edit', $period->id) }}" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                <form action="{{ route('admin.ramadan-periods.delete', $period->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cette période?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center">Aucune période de Ramadan trouvée</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@include('footer')
