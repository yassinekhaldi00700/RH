@include('header')

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Modifier les Jours de Travail: {{ $user->employee_name }}</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.user-workdays.update', $user->id) }}" method="POST">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label>Employé</label>
                        <input type="text" class="form-control" value="{{ $user->employee_code }} - {{ $user->employee_name }}" disabled>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label>Jours de Travail * (Sélectionner au moins un jour)</label>
                        <div class="form-check">
                            @foreach($weekdays as $key => $day)
                                <div class="custom-control custom-checkbox d-inline-block mr-3">
                                    <input type="checkbox" class="custom-control-input" id="day_{{ $key }}" name="workdays[]" value="{{ $key }}" 
                                        {{ in_array($key, old('workdays', $existingWorkdays)) ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="day_{{ $key }}">{{ $day }}</label>
                                </div>
                            @endforeach
                        </div>
                        @error('workdays')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Mettre à jour</button>
                    <a href="{{ route('admin.user-workdays') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('footer')
