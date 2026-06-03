@include('header')

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">
        {{ isset($type) && $type == 'ramadan' ? 'Créer un Horaire Ramadan' : 'Créer un Nouvel Horaire' }}
    </h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.schedules.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="{{ $type ?? 'normal' }}">
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="name">Nom de l'horaire *</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h5 class="mt-4 mb-3">Horaires de travail</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="morning_start">Début Matin *</label>
                        <input type="time" step="1" class="form-control @error('morning_start') is-invalid @enderror" id="morning_start" name="morning_start" value="{{ old('morning_start', '09:00:00') }}" required>
                        @error('morning_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="lunch_start">Début Déjeuner *</label>
                        <input type="time" step="1" class="form-control @error('lunch_start') is-invalid @enderror" id="lunch_start" name="lunch_start" value="{{ old('lunch_start', '12:30:00') }}" required>
                        @error('lunch_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="lunch_end">Fin Déjeuner *</label>
                        <input type="time" step="1" class="form-control @error('lunch_end') is-invalid @enderror" id="lunch_end" name="lunch_end" value="{{ old('lunch_end', '14:00:00') }}" required>
                        @error('lunch_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="workday_end">Fin Journée *</label>
                        <input type="time" step="1" class="form-control @error('workday_end') is-invalid @enderror" id="workday_end" name="workday_end" value="{{ old('workday_end', '18:00:00') }}" required>
                        @error('workday_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h5 class="mt-4 mb-3">Seuils de tolérance</h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label for="morning_threshold">Seuil Matin *</label>
                        <input type="time" step="1" class="form-control @error('morning_threshold') is-invalid @enderror" id="morning_threshold" name="morning_threshold" value="{{ old('morning_threshold', '09:00:00') }}" required>
                        @error('morning_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="lunch_threshold">Seuil Déjeuner *</label>
                        <input type="time" step="1" class="form-control @error('lunch_threshold') is-invalid @enderror" id="lunch_threshold" name="lunch_threshold" value="{{ old('lunch_threshold', '12:15:00') }}" required>
                        @error('lunch_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="afternoon_threshold">Seuil Après-midi *</label>
                        <input type="time" step="1" class="form-control @error('afternoon_threshold') is-invalid @enderror" id="afternoon_threshold" name="afternoon_threshold" value="{{ old('afternoon_threshold', '14:15:00') }}" required>
                        @error('afternoon_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="evening_threshold">Seuil Soir *</label>
                        <input type="time" step="1" class="form-control @error('evening_threshold') is-invalid @enderror" id="evening_threshold" name="evening_threshold" value="{{ old('evening_threshold', '17:45:00') }}" required>
                        @error('evening_threshold')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <h5 class="mt-4 mb-3">Pénalités (en minutes)</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="morning_penalty">Pénalité Matin *</label>
                        <input type="number" class="form-control @error('morning_penalty') is-invalid @enderror" id="morning_penalty" name="morning_penalty" value="{{ old('morning_penalty', 30) }}" min="0" required>
                        @error('morning_penalty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="lunch_penalty">Pénalité Déjeuner *</label>
                        <input type="number" class="form-control @error('lunch_penalty') is-invalid @enderror" id="lunch_penalty" name="lunch_penalty" value="{{ old('lunch_penalty', 15) }}" min="0" required>
                        @error('lunch_penalty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="afternoon_penalty">Pénalité Après-midi *</label>
                        <input type="number" class="form-control @error('afternoon_penalty') is-invalid @enderror" id="afternoon_penalty" name="afternoon_penalty" value="{{ old('afternoon_penalty', 15) }}" min="0" required>
                        @error('afternoon_penalty')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Créer</button>
                    <a href="{{ route('admin.schedules', ['type' => $type ?? 'normal']) }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('footer')
