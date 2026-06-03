@include('header')

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        {{ isset($type) && $type == 'ramadan' ? 'Ajouter un Employé - Ramadan' : 'Ajouter un Employé' }}
    </h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.user-schedules.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="{{ $type ?? 'normal' }}">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="employee_code">Employé *</label>
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
                    <div class="col-md-4 mb-3">
                        <label for="schedule_id">Horaire</label>
                        <select class="form-control @error('schedule_id') is-invalid @enderror" id="schedule_id" name="schedule_id">
                            <option value="">Standard</option>
                            @foreach($schedules as $schedule)
                                <option value="{{ $schedule->id }}" {{ old('schedule_id') == $schedule->id ? 'selected' : '' }}>{{ $schedule->name }}</option>
                            @endforeach
                        </select>
                        @error('schedule_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Créer</button>
                    <a href="{{ route('admin.user-schedules', ['type' => $type ?? 'normal']) }}" class="btn btn-secondary">Annuler</a>
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
    // Initialize Select2 for employee search
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

    // Update full name when employee is selected
    $('#employee_code').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var fullName = selectedOption.attr('data-name');
        $('#full_name').val(fullName || '');
    });
});
</script>

@include('footer')
