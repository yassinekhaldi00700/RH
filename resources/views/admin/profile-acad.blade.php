@include('header')

<div class="container-fluid">

    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800"><i class="fas fa-id-card mr-2"></i>Profil AC / AD</h1>
        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addPersonModal">
            <i class="fas fa-plus mr-1"></i> Ajouter un personnel
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if($biostarFailed)
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-plug mr-2"></i><strong>BioStar inaccessible</strong> — Impossible de récupérer la liste du serveur BioStar. Le compteur "Non défini" peut être inexact.
        </div>
    @endif

    {{-- Stats --}}
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card border-left-dark shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-dark text-uppercase mb-1">Total BioStar</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $defined->count() + count($undefined) }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-database fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total défini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $defined->count() }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-users fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">AC</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $defined->where('categorie','AC')->count() }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-check fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">AD</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ $defined->where('categorie','AD')->count() }}</div>
                        </div>
                        <div class="col-auto"><i class="fas fa-user-tie fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Non défini</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">{{ count($undefined) }}</div>
                            @if(!$biostarFailed && count($undefined) > 0)
                            <div class="text-xs text-warning mt-1"><i class="fas fa-arrow-down mr-1"></i>À définir ci-dessous</div>
                            @endif
                        </div>
                        <div class="col-auto"><i class="fas fa-user-slash fa-2x text-gray-300"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Defined Personnel Table --}}
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-list mr-1"></i> Personnel défini</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 w-100" id="definedTable">
                    <thead class="thead-light">
                        <tr>
                            <th>ID BioStar</th>
                            <th>Nom</th>
                            <th style="width:160px">Catégorie</th>
                            <th style="width:100px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($defined as $person)
                        <tr>
                            <td>{{ $person->id_biostar }}</td>
                            <td>{{ $person->nom }}</td>
                            <td>
                                <form action="{{ route('admin.profile-acad.update', $person->id) }}" method="POST" class="d-flex align-items-center">
                                    @csrf @method('PUT')
                                    <select name="categorie" class="form-control form-control-sm mr-2">
                                        <option value="AC" {{ $person->categorie === 'AC' ? 'selected' : '' }}>AC</option>
                                        <option value="AD" {{ $person->categorie === 'AD' ? 'selected' : '' }}>AD</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-success" title="Enregistrer">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form action="{{ route('admin.profile-acad.destroy', $person->id) }}" method="POST"
                                      onsubmit="return confirm('Supprimer {{ addslashes($person->nom) }} ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Undefined Personnel --}}
    @if(count($undefined) > 0)
    <div class="card shadow mb-4 border-left-warning">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-warning">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Personnel BioStar non défini ({{ count($undefined) }})
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="alert alert-warning m-3 mb-1">
                <i class="fas fa-info-circle mr-1"></i>
                Ces personnels existent dans BioStar mais n'ont pas encore de catégorie AC/AD définie. Cliquez sur <strong>Définir</strong> pour les ajouter.
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover mb-0 w-100" id="undefinedTable">
                    <thead class="thead-light">
                        <tr>
                            <th>ID BioStar</th>
                            <th>Nom</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($undefined as $person)
                        <tr>
                            <td>{{ $person['user_id'] }}</td>
                            <td>{{ $person['user_name'] }}</td>
                            <td>
                                <button class="btn btn-sm btn-warning define-btn"
                                    data-id="{{ $person['user_id'] }}"
                                    data-nom="{{ $person['user_name'] }}"
                                    data-toggle="modal" data-target="#defineModal">
                                    <i class="fas fa-plus-circle mr-1"></i> Définir
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

</div>

{{-- Add Person Modal --}}
<div class="modal fade" id="addPersonModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.profile-acad.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus mr-2"></i>Ajouter un personnel</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Rechercher dans BioStar</label>
                        <input type="text" id="biostarSearch" class="form-control" placeholder="Tapez un nom...">
                        <div id="biostarResults" style="border:1px solid #ddd; max-height:200px; overflow-y:auto; display:none; border-radius:4px;"></div>
                    </div>
                    <input type="hidden" name="id_biostar" id="selectedId">
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" id="selectedNom" class="form-control" readonly required>
                    </div>
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="categorie" class="form-control" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="AC">AC</option>
                            <option value="AD">AD</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Define Modal (for undefined people) --}}
<div class="modal fade" id="defineModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.profile-acad.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-tag mr-2"></i>Définir la catégorie</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_biostar" id="defineId">
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" id="defineNom" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Catégorie</label>
                        <select name="categorie" class="form-control" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="AC">AC</option>
                            <option value="AD">AD</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">Définir</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('footer')

<link href="{{ asset('vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/jquery-easing/jquery.easing.min.js') }}"></script>
<script src="{{ asset('js/sb-admin-2.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>

<script>
// ── DataTable for defined personnel ──────────────────────────────────────
$(document).ready(function () {
    $('#definedTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        columnDefs: [{ orderable: false, targets: [2, 3] }],
        language: {
            url: '',
            search: 'Rechercher:',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' },
            zeroRecords: 'Aucun résultat trouvé'
        }
    });
});

// ── DataTable for undefined personnel ────────────────────────────────────
if ($('#undefinedTable tbody tr').length > 0) {
    $('#undefinedTable').DataTable({
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        columnDefs: [{ orderable: false, targets: [2] }],
        language: {
            search: 'Rechercher:',
            lengthMenu: 'Afficher _MENU_ entrées',
            info: 'Affichage de _START_ à _END_ sur _TOTAL_ entrées',
            infoEmpty: 'Aucune entrée',
            paginate: { first: 'Premier', last: 'Dernier', next: 'Suivant', previous: 'Précédent' },
            zeroRecords: 'Aucun résultat trouvé'
        }
    });
}

// ── "Définir" button fills the define modal (delegated for DataTables) ─────
$(document).on('click', '.define-btn', function () {
    document.getElementById('defineId').value  = this.dataset.id;
    document.getElementById('defineNom').value = this.dataset.nom;
});

// ── BioStar search in Add modal (uses /api/persons live) ─────────────────
var definedIds = @json($defined->pluck('id_biostar')->map(fn($v) => (string)$v)->values()->toArray());
var searchTimeout = null;

document.getElementById('biostarSearch').addEventListener('input', function () {
    const q = this.value.trim();
    const resultsDiv = document.getElementById('biostarResults');
    if (q.length < 2) { resultsDiv.style.display = 'none'; return; }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(function () {
        resultsDiv.innerHTML = '<div style="padding:8px 12px;color:#888;"><i class="fas fa-spinner fa-spin mr-1"></i> Chargement...</div>';
        resultsDiv.style.display = 'block';

        fetch('/api/persons?search=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(data => {
                if (data.error) {
                    resultsDiv.innerHTML = '<div style="padding:8px 12px;color:red;"><i class="fas fa-exclamation-triangle mr-1"></i>' + data.error + '</div>';
                    return;
                }
                const persons = data;
                if (!persons.length) {
                    resultsDiv.innerHTML = '<div style="padding:8px 12px;color:#888;">Aucun résultat</div>';
                    return;
                }
                resultsDiv.innerHTML = persons.map(p => {
                    const isDefined = definedIds.includes(String(p.user_id));
                    return `<div class="biostar-result-item" data-id="${p.user_id}" data-nom="${p.user_name}"
                          style="padding:8px 12px;cursor:pointer;${isDefined ? 'color:#888;' : ''}"
                          onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background=''">
                        <i class="fas fa-${isDefined ? 'check-circle text-success' : 'user'} mr-2"></i>
                        ${p.user_name} <small>(${p.user_id})</small>
                        ${isDefined ? '<span class="badge badge-secondary ml-1">déjà défini</span>' : ''}
                    </div>`;
                }).join('');

                resultsDiv.querySelectorAll('.biostar-result-item').forEach(item => {
                    item.addEventListener('click', function () {
                        document.getElementById('selectedId').value  = this.dataset.id;
                        document.getElementById('selectedNom').value = this.dataset.nom;
                        document.getElementById('biostarSearch').value = this.dataset.nom;
                        resultsDiv.style.display = 'none';
                    });
                });
            })
            .catch(err => {
                resultsDiv.innerHTML = '<div style="padding:8px 12px;color:red;"><i class="fas fa-exclamation-triangle mr-1"></i>Erreur: ' + err.message + '</div>';
            });
    }, 300);
});

document.addEventListener('click', function (e) {
    if (!e.target.closest('#addPersonModal')) {
        document.getElementById('biostarResults').style.display = 'none';
    }
});
</script>
