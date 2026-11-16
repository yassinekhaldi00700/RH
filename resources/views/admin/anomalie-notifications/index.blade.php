@include('header')

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-envelope-open-text mr-2"></i>Notifications Anomalies</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Global switch --}}
    <div class="card shadow mb-4 {{ $enabled ? 'border-left-success' : 'border-left-danger' }}">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <h6 class="m-0 font-weight-bold {{ $enabled ? 'text-success' : 'text-danger' }}">
                    <i class="fas fa-{{ $enabled ? 'check-circle' : 'ban' }} mr-1"></i>
                    Envoi automatique : {{ $enabled ? 'ACTIVÉ' : 'DÉSACTIVÉ' }}
                </h6>
                <p class="text-muted mb-0 mt-1" style="font-size:13px;">
                    Ce bouton autorise ou bloque l'envoi — il ne construit ni n'envoie rien tout seul.
                    Utilisez "Construire la liste du jour" puis cochez/décochez les personnes avant "Envoyer maintenant".
                    @if(!$enabled)
                        <strong class="text-danger">Tant que c'est désactivé, aucun email n'est envoyé, même en cliquant Envoyer maintenant.</strong>
                    @endif
                </p>
            </div>
            <form action="{{ route('admin.anomalie-notifications.toggle') }}" method="POST">
                @csrf
                <button type="submit" class="btn {{ $enabled ? 'btn-danger' : 'btn-success' }}">
                    <i class="fas fa-power-off mr-1"></i>
                    {{ $enabled ? 'Tout arrêter' : 'Réactiver' }}
                </button>
            </form>
        </div>
    </div>

    {{-- Build today's list --}}
    <form action="{{ route('admin.anomalie-notifications.build') }}" method="POST" class="mb-3">
        @csrf
        <button type="submit" class="btn btn-info">
            <i class="fas fa-sync-alt mr-1"></i> Construire la liste du jour (lundi → vendredi précédent, sinon → veille)
        </button>
    </form>

    {{-- View toggle --}}
    <ul class="nav nav-pills mb-3">
        <li class="nav-item">
            <a class="nav-link {{ !$showLast7 ? 'active' : '' }}" href="{{ route('admin.anomalie-notifications') }}">
                <i class="fas fa-calendar-day mr-1"></i> Vue par jour
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $showLast7 ? 'active' : '' }}" href="{{ route('admin.anomalie-notifications', ['last7' => 1]) }}">
                <i class="fas fa-history mr-1"></i> 7 derniers jours
            </a>
        </li>
    </ul>

    @if(!$showLast7)
    {{-- Date picker --}}
    <form method="GET" action="{{ route('admin.anomalie-notifications') }}" class="form-inline mb-3">
        <label class="mr-2 font-weight-bold">Journée :</label>
        <select name="date" class="form-control mr-2" onchange="this.form.submit()">
            @forelse($dates as $d)
                <option value="{{ $d }}" {{ $d === $selectedDate ? 'selected' : '' }}>{{ $d }}</option>
            @empty
                <option value="">Aucune liste construite pour l'instant</option>
            @endforelse
        </select>
    </form>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list mr-1"></i>
                @if($showLast7)
                    Personnel en anomalie — 7 derniers jours
                @else
                    Personnel en anomalie — {{ $selectedDate ?? '—' }}
                @endif
                <span class="badge badge-secondary ml-2">{{ $rows->count() }}</span>
            </h6>
            @if($rows->count() > 0 && !$showLast7)
            <div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="check-all">Tout cocher</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="uncheck-all">Tout décocher</button>
            </div>
            @endif
        </div>

        @if($rows->count() === 0)
        <div class="card-body text-muted">
            @if($showLast7)
                Aucune anomalie enregistrée sur les 7 derniers jours.
            @else
                Aucune anomalie trouvée pour cette journée — ou la liste n'a pas encore été construite
                (<code>php artisan anomalie:build --date={{ $selectedDate }}</code>).
            @endif
        </div>

        @elseif($showLast7)
        {{-- Read-only history across several days: no selection, just what happened --}}
        <div class="table-responsive">
            <table class="table table-bordered table-hover mb-0 w-100">
                <thead class="thead-light">
                    <tr>
                        <th style="width:110px">Date</th>
                        <th>Nom</th>
                        <th>Email</th>
                        <th style="width:120px">Statut</th>
                        <th style="width:160px">Envoyé le</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr>
                        <td>{{ $row->work_date }}</td>
                        <td>{{ $row->nom }}</td>
                        <td>{{ $row->email }}</td>
                        <td>
                            @if($row->status === 'sent')
                                <span class="badge badge-success">Envoyé</span>
                            @elseif($row->status === 'failed')
                                <span class="badge badge-danger" title="{{ $row->error_message }}">Échec</span>
                            @elseif($row->excluded)
                                <span class="badge badge-secondary">Exclu</span>
                            @else
                                <span class="badge badge-warning">En attente</span>
                            @endif
                        </td>
                        <td>{{ $row->sent_at }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @else
        <form id="selectionForm" method="POST" action="{{ route('admin.anomalie-notifications.selection') }}">
            @csrf
            <input type="hidden" name="work_date" value="{{ $selectedDate }}">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 w-100">
                    <thead class="thead-light">
                        <tr>
                            <th style="width:40px"></th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th style="width:120px">Statut</th>
                            <th style="width:160px">Envoyé le</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $row)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" name="selected[]" value="{{ $row->id }}"
                                       {{ $row->status !== 'pending' ? 'disabled' : '' }}
                                       {{ !$row->excluded ? 'checked' : '' }}>
                            </td>
                            <td>{{ $row->nom }}</td>
                            <td>{{ $row->email }}</td>
                            <td>
                                @if($row->status === 'sent')
                                    <span class="badge badge-success">Envoyé</span>
                                @elseif($row->status === 'failed')
                                    <span class="badge badge-danger" title="{{ $row->error_message }}">Échec</span>
                                @elseif($row->excluded)
                                    <span class="badge badge-secondary">Exclu</span>
                                @else
                                    <span class="badge badge-warning">En attente</span>
                                @endif
                            </td>
                            <td>{{ $row->sent_at }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-flex justify-content-end">
                <button type="submit" class="btn btn-secondary mr-2">
                    <i class="fas fa-save mr-1"></i> Enregistrer la sélection
                </button>
                <button type="submit" formaction="{{ route('admin.anomalie-notifications.send') }}" class="btn btn-primary"
                        onclick="return confirm('Envoyer les emails maintenant pour les personnes cochées ?')">
                    <i class="fas fa-paper-plane mr-1"></i> Envoyer maintenant
                </button>
            </div>
        </form>
        @endif
    </div>
</div>

<script>
document.getElementById('check-all')?.addEventListener('click', function () {
    document.querySelectorAll('#selectionForm input[type=checkbox]:not(:disabled)').forEach(cb => cb.checked = true);
});
document.getElementById('uncheck-all')?.addEventListener('click', function () {
    document.querySelectorAll('#selectionForm input[type=checkbox]:not(:disabled)').forEach(cb => cb.checked = false);
});
</script>

@include('footer')
