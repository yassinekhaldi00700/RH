
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Admin Panel</title>
    <link href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ asset('css/sb-admin-2.min.css') }}" rel="stylesheet">
    <style>
         body {
            background:linear-gradient(90deg, #071744 0%, #071233 100%);
        }
    </style>
</head>
<body class="">

    <div class="container mt-5">

        <!-- Outer Row -->
        <div class="row justify-content-center">

            <div class="col-xl-5 col-lg-6 col-md-7">

                <div class="card o-hidden border-0 shadow-lg my-5">
                    <div class="card-body p-5">

                        <!-- Register Form -->
                        <div class="text-center">
                            <div class="" ><img src="{{ asset('img/images.png') }}" alt="" style="height:70px; width 140px;"></div>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                      <form method="POST" action="{{ url('/register') }}" class="user">
    @csrf

    <div class="form-group">
        <input type="text" name="name" class="form-control form-control-user"
               placeholder="Full Name" required>
    </div>

    <div class="form-group">
        <input type="email" name="email" class="form-control form-control-user"
               placeholder="Email Address" required>
    </div>

    <div class="form-group">
        <input type="password" name="password" class="form-control form-control-user"
               placeholder="Password" required>
    </div>

    <div class="form-group">
        <input type="password" name="password_confirmation" class="form-control form-control-user"
               placeholder="Confirm Password" required>
    </div>

    <button type="submit" class="btn btn-primary btn-user btn-block">
        Register
    </button>
</form>

                        <hr>
                        <div class="text-center">
                            <a class="small" href="{{ route('login') }}">Already have an account? Login!</a>
                        </div>
                        <div class="text-center mt-2">
                            <small>&copy; Copyright © ATTENDANCE UEMF {{ date('Y') }}</small>
                        </div>

                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- SB Admin 2 Scripts -->
    <script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('vendor/jquery-easing/jquery.easing.min.js') }}"></script>
    <script src="{{ asset('js/sb-admin-2.min.js') }}"></script>
</body>
</html>