@include('header')

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Définir les Jours de Travail</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.user-workdays.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="user_id">Employé *</label>
                        <select class="form-control @error('user_id') is-invalid @enderror" id="user_id" name="user_id" required>
                            <option value="">Sélectionner un employé</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" {{ old('user_id') == $user->id ? 'selected' : '' }}>{{ $user->employee_code }} - {{ $user->full_name }}</option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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

@include('footer')
