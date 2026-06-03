@include('header')

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container .select2-selection--single { height: 46px; border: 2px solid #e0e0e0; border-radius: 0.5rem; }
    .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: 42px; padding-left: 1rem; color: #616161; }
    .select2-container--default .select2-selection--single .select2-selection__arrow { height: 44px; }
    .select2-container--default.select2-container--focus .select2-selection--single { border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
</style>

<!-- Loading Spinner Overlay -->
<div id="loadingSpinner" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 9999; justify-content: center; align-items: center;">
    <div style="text-align: center;">
        <div class="spinner-border text-light" role="status" style="width: 5rem; height: 5rem; border-width: 0.5rem;">
            <span class="sr-only">Chargement...</span>
        </div>
        <h3 style="color: white; margin-top: 20px; font-weight: 300;">Chargement en cours...</h3>
        <p style="color: rgba(255,255,255,0.7); margin-top: 10px;">Veuillez patienter, traitement des données...</p>
    </div>
</div>

<div class="container-fluid">
<div class="main-container">
    <div class="header-section">
        <h1 class="main-title">
            <i class="fas fa-chart-line"></i>
            Système de Gestion des Présences
        </h1>
        <p class="subtitle">Analyse et exportation des données d'attendance</p>
    </div>

    <!-- Filters Card -->
    <div class="card filters-card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtres de Recherche</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('date.filter') }}" id="attendance-form">
                @csrf
                <div class="filters-grid">
                    <!-- Category Selection -->
                    <div class="filter-item">
                        <label for="category">
                            <i class="fas fa-users"></i>
                            Catégorie
                        </label>
                        <select id="category" name="category" class="form-control elegant-select" required>
                            @if(($userDroit ?? 1) == 0 || ($userDroit ?? 1) == 1)
                            <option value="personnel" {{ (old('category', $category ?? '') == 'personnel') ? 'selected' : '' }}>Personnel</option>
                            @endif
                            @if(($userDroit ?? 1) == 0 || ($userDroit ?? 1) == 2)
                            <option value="doctorants" {{ (old('category', $category ?? '') == 'doctorants') ? 'selected' : '' }}>Doctorants</option>
                            @endif
                            @if(($userDroit ?? 1) == 0 || ($userDroit ?? 1) == 3)
                            <option value="complex" {{ (old('category', $category ?? '') == 'complex') ? 'selected' : '' }}>Complex</option>
                            @endif
                            @if(($userDroit ?? 1) == 0)
                            <option value="vacataire_emadu" {{ (old('category', $category ?? '') == 'vacataire_emadu') ? 'selected' : '' }}>Vacataires EMADU</option>
                            @endif
                        </select>
                    </div>

                    <!-- Group Selection -->
                    <div class="filter-item">
                        <label for="group_id">
                            <i class="fas fa-building"></i>
                            Groupe
                        </label>
                        <select id="group_id" name="group_id" class="form-control elegant-select">
                            <option value="">Tous les groupes</option>
                            <!-- Groups will be populated via AJAX -->
                        </select>
                    </div>

                    <!-- Person Search -->
                    <div class="filter-item">
                        <label for="person_id">
                            <i class="fas fa-user"></i>
                            Personne
                        </label>
                        <select id="person_id" name="person_id" class="form-control select2-person">
                            <option value="">Tous les employés</option>
                        </select>
                    </div>

                    <!-- Date Range -->
                    <div class="filter-item date-item">
                        <label>
                            <i class="fas fa-calendar-alt"></i>
                            Période
                        </label>
                        <div class="date-range-container">
                            <input type="date" id="start-date" name="start_date" class="form-control elegant-input" required value="{{ old('start_date', $start_date ?? '') }}">
                            <span class="date-separator">à</span>
                            <input type="date" id="end-date" name="end_date" class="form-control elegant-input" required value="{{ old('end_date', $end_date ?? '') }}">
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-section">
                    <button type="submit" class="btn btn-primary elegant-btn">
                        <i class="fas fa-search"></i>
                        Rechercher
                    </button>
                    <button type="button" id="reset-filters" class="btn btn-secondary elegant-btn">
                        <i class="fas fa-undo"></i>
                        Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Columns Selection Card -->
    <div class="card columns-card">
        <div class="card-header">
            <h3><i class="fas fa-columns"></i> Colonnes à Afficher</h3>
            <div class="column-actions">
                <button type="button" id="select-all" class="btn btn-info btn-sm elegant-btn">
                    <i class="fas fa-check-square"></i> Tout
                </button>
                <button type="button" id="select-essential" class="btn btn-warning btn-sm elegant-btn">
                    <i class="fas fa-star"></i> Essentielles
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="columns-grid">
                <!-- Basic Info -->
                <div class="column-category">
                    <h4 class="category-title">
                        <i class="fas fa-info-circle text-primary"></i>
                        Informations de Base
                    </h4>
                    <div class="checkbox-group">
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-date" value="date" {{ in_array('date', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-date">
                                <span class="checkmark"></span>
                                <i class="fas fa-calendar"></i>
                                Date
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-nom" value="nom" {{ in_array('nom', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-nom">
                                <span class="checkmark"></span>
                                <i class="fas fa-user"></i>
                                Nom
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-id_utilisateur" value="id_utilisateur" {{ in_array('id_utilisateur', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-id_utilisateur">
                                <span class="checkmark"></span>
                                <i class="fas fa-id-badge"></i>
                                ID Utilisateur
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-departement" value="departement" {{ in_array('departement', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-departement">
                                <span class="checkmark"></span>
                                <i class="fas fa-building"></i>
                                Département
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Status & Exceptions -->
                <div class="column-category">
                    <h4 class="category-title">
                        <i class="fas fa-exclamation-triangle text-warning"></i>
                        Statut & Exceptions
                    </h4>
                    <div class="checkbox-group">
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-status" value="status" {{ in_array('status', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-status">
                                <span class="checkmark"></span>
                                <i class="fas fa-info"></i>
                                Statut
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-exception" value="exception" {{ in_array('exception', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-exception">
                                <span class="checkmark"></span>
                                <i class="fas fa-exclamation"></i>
                                Exception
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Time Tracking -->
                <div class="column-category">
                    <h4 class="category-title">
                        <i class="fas fa-clock text-info"></i>
                        Horaires
                    </h4>
                    <div class="checkbox-group">
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-premier_entree" value="premier_entree" {{ in_array('premier_entree', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-premier_entree">
                                <span class="checkmark"></span>
                                <i class="fas fa-sign-in-alt"></i>
                                Première Entrée
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-sortie_final" value="sortie_final" {{ in_array('sortie_final', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-sortie_final">
                                <span class="checkmark"></span>
                                <i class="fas fa-sign-out-alt"></i>
                                Sortie Finale
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="column-category">
                    <h4 class="category-title">
                        <i class="fas fa-chart-bar text-success"></i>
                        Métriques
                    </h4>
                    <div class="checkbox-group">
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-r1" value="r1" {{ in_array('r1', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-r1">
                                <span class="checkmark"></span>
                                <i class="fas fa-hourglass-end text-danger"></i>
                                Retard 1
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-sa1" value="sa1" {{ in_array('sa1', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-sa1">
                                <span class="checkmark"></span>
                                <i class="fas fa-fast-forward text-info"></i>
                                Sortie Avancée 1
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-r2" value="r2" {{ in_array('r2', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-r2">
                                <span class="checkmark"></span>
                                <i class="fas fa-hourglass-end text-danger"></i>
                                Retard 2
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-sa2" value="sa2" {{ in_array('sa2', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-sa2">
                                <span class="checkmark"></span>
                                <i class="fas fa-fast-forward text-info"></i>
                                Sortie Avancée 2
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-sum_sa" value="sum_sa" {{ in_array('sum_sa', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-sum_sa">
                                <span class="checkmark"></span>
                                <i class="fas fa-calculator text-info"></i>
                                Total SA
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-sum_r" value="sum_r" {{ in_array('sum_r', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-sum_r">
                                <span class="checkmark"></span>
                                <i class="fas fa-calculator text-danger"></i>
                                Total Retards
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-sum_gen" value="sum_gen" {{ in_array('sum_gen', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-sum_gen">
                                <span class="checkmark"></span>
                                <i class="fas fa-sum text-purple"></i>
                                Total Général
                            </label>
                        </div>
                        <div class="elegant-checkbox">
                            <input type="checkbox" name="columns[]" id="col-hour_supp" value="hour_supp" {{ in_array('hour_supp', $selectedColumns ?? []) ? 'checked' : '' }}>
                            <label for="col-hour_supp">
                                <span class="checkmark"></span>
                                <i class="fas fa-plus-circle text-success"></i>
                                Heures Supp.
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        </form>
                    
        

    <!-- Results Table Card -->
    <div class="card results-card">
        <div class="card-header">
            <h3><i class="fas fa-table"></i> Aperçu des Données</h3>
            <div class="results-info">
                @if(isset($attendanceData['processed_data']))
                    <span class="badge badge-primary">
                        <i class="fas fa-database"></i>
                        {{ count($attendanceData['processed_data']) }} enregistrements
                    </span>
                @endif
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive elegant-table">
                <table class="table" id="dataTable" width="100%" cellspacing="0">
                        <thead>
    <tr>
        @if(in_array('date', $selectedColumns ?? [])) <th>Date</th> @endif
        @if(in_array('nom', $selectedColumns ?? [])) <th>Nom</th> @endif
        @if(in_array('id_utilisateur', $selectedColumns ?? [])) <th>ID utilisateur</th> @endif
        @if(in_array('departement', $selectedColumns ?? [])) <th>Département</th> @endif
        @if(in_array('status', $selectedColumns ?? [])) <th>Status</th> @endif
        @if(in_array('exception', $selectedColumns ?? [])) <th>Exception</th> @endif
        @if(in_array('premier_entree', $selectedColumns ?? [])) <th>Premier Entrée</th> @endif
        @if(in_array('sortie_final', $selectedColumns ?? [])) <th>Sortie Final</th> @endif
        @if(in_array('r1', $selectedColumns ?? [])) <th>R1</th> @endif
        @if(in_array('sa1', $selectedColumns ?? [])) <th>SA1</th> @endif
        @if(in_array('r2', $selectedColumns ?? [])) <th>R2</th> @endif
        @if(in_array('sa2', $selectedColumns ?? [])) <th>SA2</th> @endif
        @if(in_array('sum_sa', $selectedColumns ?? [])) <th>SUM SA</th> @endif
        @if(in_array('sum_r', $selectedColumns ?? [])) <th>SUM R</th> @endif
        @if(in_array('sum_gen', $selectedColumns ?? [])) <th>SUM GEN</th> @endif
        @if(in_array('hour_supp', $selectedColumns ?? [])) <th>Hour Supp</th> @endif
    </tr>
</thead>
                        <tbody>
@if(isset($attendanceData['processed_data']) && count($attendanceData['processed_data']) > 0)
    @foreach($attendanceData['processed_data'] as $record)
        <tr class="record-row" data-record-id="{{ $loop->index }}">
            @if(in_array('date', $selectedColumns ?? [])) <td>{{ $record['Date'] ?? '' }}</td> @endif
            @if(in_array('nom', $selectedColumns ?? [])) <td>{{ $record['Nom'] ?? '' }}</td> @endif
            @if(in_array('id_utilisateur', $selectedColumns ?? [])) <td>{{ $record['ID utilisateur'] ?? '' }}</td> @endif
            @if(in_array('departement', $selectedColumns ?? [])) <td>{{ $record['Département'] ?? '' }}</td> @endif
            @if(in_array('status', $selectedColumns ?? [])) <td>{{ $record['Status'] ?? '' }}</td> @endif
            @if(in_array('exception', $selectedColumns ?? [])) <td>{{ $record['Exception'] ?? '' }}</td> @endif
            @if(in_array('premier_entree', $selectedColumns ?? [])) <td>{{ $record['Premier Entree'] ?? '' }}</td> @endif
            @if(in_array('sortie_final', $selectedColumns ?? [])) <td>{{ $record['Sortie Final'] ?? '' }}</td> @endif
            @if(in_array('r1', $selectedColumns ?? [])) <td>{{ $record['Retard 1 '] ?? '' }}</td> @endif
            @if(in_array('sa1', $selectedColumns ?? [])) <td>{{ $record['Sortie en avance 1 '] ?? '' }}</td> @endif
            @if(in_array('r2', $selectedColumns ?? [])) <td>{{ $record['Retard 2 '] ?? '' }}</td> @endif
            @if(in_array('sa2', $selectedColumns ?? [])) <td>{{ $record['Sortie en avance 2 '] ?? '' }}</td> @endif
            @if(in_array('sum_sa', $selectedColumns ?? [])) <td>{{ $record['la somme Sortie en avance'] ?? '' }}</td> @endif
            @if(in_array('sum_r', $selectedColumns ?? [])) <td>{{ $record['la somme Retard'] ?? '' }}</td> @endif
            @if(in_array('sum_gen', $selectedColumns ?? [])) <td>{{ $record['la somme du Retards General'] ?? '' }}</td> @endif
            @if(in_array('hour_supp', $selectedColumns ?? [])) <td>{{ $record['Heure Supp'] ?? '' }}</td> @endif

            <!-- Hidden inputs remain unchanged -->
            <input type="hidden" class="record-e1" value="{{ $record['e1'] ?? '' }}">
            <input type="hidden" class="record-s1" value="{{ $record['s1'] ?? '' }}">
            <input type="hidden" class="record-e2" value="{{ $record['e2'] ?? '' }}">
            <input type="hidden" class="record-s2" value="{{ $record['s2'] ?? '' }}">
            <input type="hidden" class="record-e3" value="{{ $record['e3'] ?? '' }}">
            <input type="hidden" class="record-s3" value="{{ $record['s3'] ?? '' }}">
            <input type="hidden" class="record-e4" value="{{ $record['e4'] ?? '' }}">
            <input type="hidden" class="record-s4" value="{{ $record['s4'] ?? '' }}">
        </tr>
    @endforeach
@else
    <tr>
        <td colspan="{{ count($selectedColumns ?? []) }}" class="text-center">Aucune donnée à afficher.</td>
    </tr>
@endif
</tbody>    
                    </table>
                </div>
            </div>
        </div>

    <!-- Export Actions Card -->
    <div class="card export-card">
        <div class="card-header">
            <h3><i class="fas fa-download"></i> Options d'Exportation</h3>
        </div>
        <div class="card-body">
            <div class="export-grid">
                <div class="export-section">
                    <h4><i class="fas fa-file-excel text-success"></i> Export Excel</h4>
                    <div class="export-buttons">
                        <button id="download-filtered" class="btn btn-success elegant-btn">
                            <i class="fas fa-filter"></i>
                            Données Filtrées
                        </button>
                        <button id="download-selected" class="btn btn-outline-success elegant-btn">
                            <i class="fas fa-columns"></i>
                            Colonnes Sélectionnées
                        </button>
                    </div>
                </div>
                <div class="export-section">
                    <h4><i class="fas fa-chart-line text-info"></i> Rapports</h4>
                    <div class="export-buttons">
                        <button id="download-cumul" class="btn btn-info elegant-btn">
                            <i class="fas fa-chart-bar"></i>
                            Rapport Cumulatif
                        </button>
                        <button id="download-summary" class="btn btn-outline-info elegant-btn">
                            <i class="fas fa-clipboard-list"></i>
                            Résumé
                        </button>
                        @if(($category ?? '') === 'personnel')
                        <button id="download-cumul-personnel" class="btn btn-danger elegant-btn">
                            <i class="fas fa-table"></i>
                            Cumul
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
</div>

<!-- Modal for detailed information -->
<div class="modal fade" id="recordDetailModal" tabindex="-1" role="dialog" aria-labelledby="recordDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="recordDetailModalLabel">Record Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <tr>
                        <th>Entry 1 (e1)</th>
                        <td id="modal-e1"></td>
                    </tr>
                    <tr>
                        <th>Exit 1 (s1)</th>
                        <td id="modal-s1"></td>
                    </tr>
                    <tr>
                        <th>Entry 2 (e2)</th>
                        <td id="modal-e2"></td>
                    </tr>
                    <tr>
                        <th>Exit 2 (s2)</th>
                        <td id="modal-s2"></td>
                    </tr>

                     <tr>
                        <th>Entry 3 (e3)</th>
                        <td id="modal-e3"></td>
                    </tr>
                    <tr>
                        <th>Exit 3 (s3)</th>
                        <td id="modal-s3"></td>
                    </tr>
                    <tr>
                        <th>Entry 4 (e4)</th>
                        <td id="modal-e4"></td>
                    </tr>
                    <tr>
                        <th>Exit 4 (s4)</th>
                        <td id="modal-s4"></td>
                    </tr>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap core JavaScript-->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Core plugin JavaScript-->
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>

<!-- DataTables JavaScript -->
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Custom scripts for all pages-->
<script src="js/sb-admin-2.min.js"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Person data from server (used to populate person dropdown by category) -->
<script>
    var allPersonsData = @json($persons ?? []);
    var preSelectedPersonId = '{{ old('person_id', $person_id ?? '') }}';
    var preSelectedCategory = '{{ old('category', $category ?? '') }}';
</script>

<!-- Initialize DataTables -->
<script>
    $(document).ready(function() {
        // Initialize DataTable with elegant styling
        var table = $('#dataTable').DataTable({
            "paging": true,
            "searching": true,
            "ordering": true,
            "info": true,
            "lengthChange": true,
            "pageLength": 25,
            "lengthMenu": [[5, 10, 25, 50, 100, -1], [5, 10, 25, 50, 100, "Tous"]],
            "language": {
                "lengthMenu": "Afficher _MENU_ entrées par page",
                "search": "Rechercher dans le tableau:",
                "paginate": {
                    "first": "Premier",
                    "last": "Dernier",
                    "next": "Suivant",
                    "previous": "Précédent"
                },
                "info": "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                "infoEmpty": "Aucune entrée disponible",
                "infoFiltered": "(filtré à partir de _MAX_ entrées au total)",
                "emptyTable": "Aucune donnée disponible"
            }
        });

        // Load initial groups and persons by current category
        loadGroups();
        updatePersonDropdown($('#category').val() || 'personnel');

        // Initialize Select2 for person search
        $('.select2-person').select2({
            placeholder: 'Rechercher un employé...',
            allowClear: true,
            language: {
                noResults: function() { return 'Aucun résultat trouvé'; },
                searching: function() { return 'Recherche...'; }
            }
        });

        // Restore selected values after form submission
        restoreSelectedValues();
        
        // Add click event for rows
        $('#dataTable tbody').on('click', 'tr', function() {
            var e1 = $(this).find('.record-e1').val();
            var s1 = $(this).find('.record-s1').val();
            var e2 = $(this).find('.record-e2').val();
            var s2 = $(this).find('.record-s2').val();
            var e3 = $(this).find('.record-e3').val();
            var s3 = $(this).find('.record-s3').val();
            var e4 = $(this).find('.record-e4').val();
            var s4 = $(this).find('.record-s4').val();
            
            // Populate modal with data
            $('#modal-e1').text(e1 || '');
            $('#modal-s1').text(s1 || '');
            $('#modal-e2').text(e2 || '');
            $('#modal-s2').text(s2 || '');
            $('#modal-e3').text(e3 || '');
            $('#modal-s3').text(s3 || '');
            $('#modal-e4').text(e4 || '');
            $('#modal-s4').text(s4 || '');
            
            // Show modal
            $('#recordDetailModal').modal('show');
        });
        
        // Make the table rows appear clickable with hover effect
        $('<style>')
            .prop('type', 'text/css')
            .html(`
                #dataTable tbody tr {
                    cursor: pointer;
                }
                #dataTable tbody tr:hover {
                    background-color: rgba(0,0,0,.075);
                }
            `)
            .appendTo('head');
    });



    // Dynamic Groups Loading
    function loadGroups() {
        var category = $('#category').val();
        
        $.ajax({
            url: '/api/groups',
            method: 'GET',
            data: { category: category },
            success: function(data) {
                var options = '<option value="">Tous les groupes</option>';
                $.each(data, function(index, group) {
                    options += '<option value="' + group.group_id + '">' + group.group_name + '</option>';
                });
                $('#group_id').html(options);
            },
            error: function() {
                console.error('Erreur lors du chargement des groupes');
            }
        });
    }

    // Populate person dropdown filtered by category
    function updatePersonDropdown(category) {
        var $select = $('#person_id');
        var previousValue = $select.val();
        $select.empty().append('<option value="">Tous les employés</option>');
        allPersonsData.forEach(function(p) {
            if (!category || p.category === category) {
                $select.append('<option value="' + p.user_id + '">' + p.user_name + '</option>');
            }
        });
        // Restore previous value if it still exists in the filtered list
        if (previousValue && $select.find('option[value="' + previousValue + '"]').length) {
            $select.val(previousValue);
        } else {
            $select.val('');
        }
        $select.trigger('change.select2');
    }

    // Category change handler
    $('#category').on('change', function() {
        loadGroups();
        updatePersonDropdown($(this).val());
    });

    // Group change handler - persons are pre-loaded by category

    // Column selection handlers
    $('#select-all').on('click', function() {
        $('input[name="columns[]"]').prop('checked', true);
    });

    $('#select-essential').on('click', function() {
        $('input[name="columns[]"]').prop('checked', false);
        $('#col-date, #col-nom, #col-id_utilisateur, #col-departement, #col-status, #col-premier_entree, #col-sortie_final').prop('checked', true);
    });

    // Show loading spinner on form submit (only after validation passes)
    $('#attendance-form').on('submit', function(e) {
        var startDate = $('#start-date').val();
        var endDate   = $('#end-date').val();
        var errorMsg  = '';
        if (!startDate || !endDate) {
            errorMsg = 'Veuillez sélectionner les deux dates de début et de fin.';
        } else if (startDate > endDate) {
            errorMsg = 'La date de début doit être antérieure ou égale à la date de fin.';
        }
        if (errorMsg) {
            $('#validationErrorMessage').text(errorMsg);
            $('#validationErrorModal').modal('show');
            e.preventDefault();
            return false;
        }
        document.getElementById('loadingSpinner').style.display = 'flex';
    });

    // Hide spinner on page restore (bfcache)
    window.addEventListener('pageshow', function() {
        document.getElementById('loadingSpinner').style.display = 'none';
    });

    // Reset filters
    $('#reset-filters').on('click', function() {
        $('#attendance-form')[0].reset();
        var defaultCategory = $('#category').find('option:first').val();
        loadGroups();
        updatePersonDropdown(defaultCategory);
        $('#person_id').val('').trigger('change.select2');
    });

    // Function to restore selected values after page load
    function restoreSelectedValues() {
        // Restore selected group (after AJAX loadGroups completes)
        var selectedGroupId = '{{ $group_id ?? "" }}';
        if (selectedGroupId) {
            setTimeout(function() {
                $('#group_id').val(selectedGroupId);
            }, 600);
        }
        // Restore selected person (after updatePersonDropdown completes)
        if (preSelectedPersonId) {
            setTimeout(function() {
                $('#person_id').val(preSelectedPersonId).trigger('change.select2');
            }, 100);
        }
    }

    // Export functions
    function getFilterParams() {
        return {
            start_date: $('#start-date').val(),
            end_date: $('#end-date').val(),
            category: $('#category').val(),
            group_id: $('#group_id').val(),
            person_id: $('#person_id').val()
        };
    }

    function getSelectedColumns() {
        return $('input[name="columns[]"]:checked').map(function() {
            return this.value;
        }).get();
    }

    $('#download-filtered').on('click', function() {
        var params = getFilterParams();
        if (!params.start_date || !params.end_date) {
            alert('Veuillez sélectionner les dates');
            return;
        }
        
        var exportCategory = $('select[name="category"]').val();
        if (exportCategory === 'complex' || exportCategory === 'vacataire_emadu') {
            params.category = exportCategory;
            params.columns = [
                'date', 'nom', 'id_utilisateur', 'departement', 'status', 'exception',
                'premier_entree', 'sortie_final', 'sum_gen'
            ];
            var url = '/export-excel?' + $.param(params) + '&format=excel&type=selected';
        } else {
            var url = '/export-excel?' + $.param(params) + '&format=excel';
        }
        window.open(url, '_blank');
    });

    $('#download-selected').on('click', function() {
        var params = getFilterParams();
        var columns = getSelectedColumns();
        
        if (!params.start_date || !params.end_date) {
            alert('Veuillez sélectionner les dates');
            return;
        }
        
        if (columns.length === 0) {
            alert('Veuillez sélectionner au moins une colonne');
            return;
        }
        
        params.columns = columns;
        var url = '/export-excel?' + $.param(params) + '&format=excel&type=selected';
        window.open(url, '_blank');
    });

    $('#download-cumul').on('click', function() {
        var params = getFilterParams();
        if (!params.start_date || !params.end_date) {
            alert('Veuillez sélectionner les dates');
            return;
        }
        
        var url = '/download-cumulative?' + $.param(params);
        window.open(url, '_blank');
    });

    $('#download-cumul-personnel').on('click', function() {
        var params = getFilterParams();
        if (!params.start_date || !params.end_date) {
            alert('Veuillez sélectionner les dates');
            return;
        }
        var url = '/download-cumul-personnel?' + $.param(params);
        window.open(url, '_blank');
    });

    $('#download-summary').on('click', function() {
        var params = getFilterParams();
        if (!params.start_date || !params.end_date) {
            alert('Veuillez sélectionner les dates');
            return;
        }
        
        var url = '/download-cumulative?' + $.param(params) + '&type=summary';
        window.open(url, '_blank');
    });
</script>


@include('footer')
<!-- Validation Error Modal -->
<div class="modal fade" id="validationErrorModal" tabindex="-1" role="dialog" aria-labelledby="validationErrorModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="validationErrorModalLabel">Erreur de validation</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" id="validationErrorMessage">
        <!-- Error message will be inserted here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>
</body>

</html>
