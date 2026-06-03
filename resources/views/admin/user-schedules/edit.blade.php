@include('header')

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Modifier l'Employé: {{ $userSchedule->full_name }}</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.user-schedules.update', $userSchedule->id) }}" method="POST">
                @csrf
                @method('PUT')
                <input type="hidden" name="type" value="{{ $userSchedule->ramadan == 1 ? 'ramadan' : 'normal' }}">
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="employee_code">Code Employé *</label>
                        <input type="text" class="form-control @error('employee_code') is-invalid @enderror" id="employee_code" name="employee_code" value="{{ old('employee_code', $userSchedule->employee_code) }}" required>
                        @error('employee_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="full_name">Nom Complet *</label>
                        <input type="text" class="form-control @error('full_name') is-invalid @enderror" id="full_name" name="full_name" value="{{ old('full_name', $userSchedule->full_name) }}" required>
                        @error('full_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="schedule_id">Horaire</label>
                        <select class="form-control @error('schedule_id') is-invalid @enderror" id="schedule_id" name="schedule_id">
                            <option value="">Standard</option>
                            @foreach($schedules as $schedule)
                                <option value="{{ $schedule->id }}" {{ old('schedule_id', $userSchedule->schedule_id) == $schedule->id ? 'selected' : '' }}>{{ $schedule->name }}</option>
                            @endforeach
                        </select>
                        @error('schedule_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    <a href="{{ route('admin.user-schedules', ['type' => $userSchedule->ramadan == 1 ? 'ramadan' : 'normal']) }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('footer')
