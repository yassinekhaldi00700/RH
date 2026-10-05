@include('header')

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Modifier une Période Spéciale</h1>
        <a href="{{ route('admin.user-schedule-periods') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.user-schedule-periods.update', $period->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="full_name" id="full_name_hidden" value="{{ old('full_name', $period->full_name) }}">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="employee_code">Employé *</label>
                        <select class="form-control select2-employee @error('employee_code') is-invalid @enderror" id="employee_code" name="employee_code" required>
                            <option value="">Sélectionner un employé</option>
                            @foreach($users as $user)
                                <option value="{{ $user->employee_code }}" data-name="{{ $user->full_name }}" {{ (old('employee_code', $period->employee_code) == $user->employee_code) ? 'selected' : '' }}>
                                    {{ $user->employee_code }} - {{ $user->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="schedule_id">Horaire *</label>
                        <select class="form-control @error('schedule_id') is-invalid @enderror" id="schedule_id" name="schedule_id" required>
                            @foreach($schedules as $schedule)
                                <option value="{{ $schedule->id }}" {{ (old('schedule_id', $period->schedule_id) == $schedule->id) ? 'selected' : '' }}>
                                    {{ $schedule->name }}{{ $schedule->ramadan ? ' (Ramadan)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('schedule_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="start_date">Date de Début *</label>
                        <input type="date"
                               class="form-control @error('start_date') is-invalid @enderror"
                               id="start_date"
                               name="start_date"
                               value="{{ old('start_date', $period->start_date) }}"
                               required>
                        @error('start_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="end_date">Date de Fin *</label>
                        <input type="date"
                               class="form-control @error('end_date') is-invalid @enderror"
                               id="end_date"
                               name="end_date"
                               value="{{ old('end_date', $period->end_date) }}"
                               required>
                        @error('end_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-warning">Mettre à jour</button>
                    <a href="{{ route('admin.user-schedule-periods') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery + Select2 -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-employee').select2({
        placeholder: 'Rechercher un employé...',
        allowClear: true,
        language: {
            noResults: function() { return "Aucun résultat trouvé"; },
            searching: function() { return "Recherche en cours..."; }
        }
    });

    $('#employee_code').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        $('#full_name_hidden').val(selectedOption.data('name') || '');
    });

    $('#start_date').on('change', function() {
        $('#end_date').attr('min', this.value);
    });
});
</script>

@include('footer')
