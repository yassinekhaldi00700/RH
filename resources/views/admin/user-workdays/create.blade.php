@include('header')

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Définir les Jours de Travail</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.user-workdays.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="user_id">Employé *</label>
                        <select class="form-control select2-employee @error('employee_code') is-invalid @enderror" id="employee_code" name="employee_code" required>
                            <option value="">Sélectionner un employé</option>
                            @foreach($users as $user)
                                <option value="{{ $user->employee_code }}" data-name="{{ $user->full_name }}" {{ old('employee_code') == $user->employee_code ? 'selected' : '' }}>{{ $user->employee_code }} - {{ $user->full_name }}</option>
                            @endforeach
                        </select>
                        
                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="full_name">Nom Complet *</label>
                        <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name') }}" required readonly>
                        @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label>Jours de Travail * (Sélectionner au moins un jour)</label>
                        <div class="form-check">
                            @foreach($weekdays as $key => $day)
                                <div class="custom-control custom-checkbox d-inline-block mr-3">
                                    <input type="checkbox" class="custom-control-input" id="day_{{ $key }}" name="workdays[]" value="{{ $key }}" {{ is_array(old('workdays')) && in_array($key, old('workdays')) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="day_{{ $key }}">{{ $day }}</label>
                                </div>
                            @endforeach
                        </div>
                        @error('workdays')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Créer</button>
                    <a href="{{ route('admin.user-workdays') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery (required for Select2) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
$(document).ready(function() {
    $('.select2-employee').select2({
        placeholder: 'Rechercher un employé...',
        allowClear: true,
        language: {
            noResults: function() {
                return "Aucun résultat trouvé";
            },
            searching: function() {
                return "Recherche en cours...";
            }
        }
    });

    $('#employee_code').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var fullName = selectedOption.attr('data-name');
        $('#full_name').val(fullName || '');
    });
});
</script>

@include('footer')



