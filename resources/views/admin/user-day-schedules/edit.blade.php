@include('header')

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Modifier Horaire Journalier</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.user-day-schedules.update', $daySchedule->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="full_name" id="full_name_hidden" value="{{ old('full_name', $daySchedule->full_name) }}">

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="employee_code">Employé *</label>
                        <select class="form-control select2-employee @error('employee_code') is-invalid @enderror" id="employee_code" name="employee_code" required>
                            <option value="">Sélectionner un employé</option>
                            @foreach($users as $user)
                                <option value="{{ $user->employee_code }}" data-name="{{ $user->full_name }}" {{ (old('employee_code', $daySchedule->employee_code) == $user->employee_code) ? 'selected' : '' }}>
                                    {{ $user->employee_code }} - {{ $user->full_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="weekday">Jour de la semaine *</label>
                        <select class="form-control @error('weekday') is-invalid @enderror" id="weekday" name="weekday" required>
                            @foreach($days as $index => $dayName)
                                <option value="{{ $index }}" {{ (old('weekday', $daySchedule->weekday) == $index) ? 'selected' : '' }}>
                                    {{ $dayName }}
                                </option>
                            @endforeach
                        </select>
                        @error('weekday')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label for="schedule_id">Horaire *</label>
                        <select class="form-control @error('schedule_id') is-invalid @enderror" id="schedule_id" name="schedule_id" required>
                            @foreach($schedules as $schedule)
                                <option value="{{ $schedule->id }}" {{ (old('schedule_id', $daySchedule->schedule_id) == $schedule->id) ? 'selected' : '' }}>
                                    {{ $schedule->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('schedule_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-warning">Mettre à jour</button>
                    <a href="{{ route('admin.user-day-schedules', ['type' => $type]) }}" class="btn btn-secondary">Annuler</a>
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
});
</script>

@include('footer')
