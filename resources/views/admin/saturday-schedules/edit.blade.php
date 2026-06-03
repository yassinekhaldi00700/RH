@include('header')

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Modifier l'Horaire Samedi</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.saturday-schedules.update', $saturdaySchedule->id) }}" method="POST">
                @csrf
                @method('PUT')
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="user_id">Employé *</label>
                        <select class="form-control select2-employee @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                            <option value="">Sélectionner un employé</option>
                            @foreach($users as $user)
                                <option value="{{ $user->employee_code }}" {{ old('user_id', $saturdaySchedule->user_id) == $user->employee_code ? 'selected' : '' }}>{{ $user->employee_code }} - {{ $user->full_name }}</option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="schedule_id">Horaire *</label>
                        <select class="form-control @error('schedule_id') is-invalid @enderror" id="schedule_id" name="schedule_id" required>
                            <option value="">Sélectionner un horaire</option>
                            @foreach($schedules as $schedule)
                                <option value="{{ $schedule->id }}" {{ old('schedule_id', $saturdaySchedule->schedule_id) == $schedule->id ? 'selected' : '' }}>{{ $schedule->name }}</option>
                            @endforeach
                        </select>
                        @error('schedule_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    <a href="{{ route('admin.saturday-schedules') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- jQuery (required for Select2) -->
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize Select2 for searchable employee dropdown
        $('.select2-employee').select2({
            placeholder: 'Rechercher un employé...',
            allowClear: true,
            width: '100%',
            language: {
                noResults: function() {
                    return "Aucun résultat trouvé";
                },
                searching: function() {
                    return "Recherche en cours...";
                },
                inputTooShort: function() {
                    return "Veuillez saisir au moins un caractère";
                }
            }
        });
    });
</script>

@include('footer')
