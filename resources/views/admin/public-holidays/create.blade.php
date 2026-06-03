@include('header')

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Ajouter un Jour Férié</h1>

    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('admin.public-holidays.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="holiday_date">Date *</label>
                        <input type="date" class="form-control @error('holiday_date') is-invalid @enderror" id="holiday_date" name="holiday_date" value="{{ old('holiday_date') }}" required>
                        @error('holiday_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8 mb-3">
                        <label for="holiday_name">Nom du Jour Férié *</label>
                        <input type="text" class="form-control @error('holiday_name') is-invalid @enderror" id="holiday_name" name="holiday_name" value="{{ old('holiday_name') }}" required>
                        @error('holiday_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="description">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Créer</button>
                    <a href="{{ route('admin.public-holidays') }}" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('footer')
