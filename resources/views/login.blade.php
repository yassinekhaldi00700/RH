<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Admin Panel</title>
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

                        <!-- Login Form -->
                        <div class="text-center">
                           <div class="" ><img src="{{ asset('img/images.png') }}" alt="" style="height:70px; width 140px;"></div>
                        </div>

                        @if ($errors->has('login_error'))
                            <div class="alert alert-danger">
                                {{ $errors->first('login_error') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ url('/login') }}" class="user">
                            @csrf

                            <div class="form-group">
                                <input type="text" name="email" class="form-control form-control-user"
                                       placeholder="Enter User ID" required>
                            </div>

                            <div class="form-group">
                                <input type="password" name="password" class="form-control form-control-user"
                                       placeholder="Password" required>
                            </div>

                            <button type="submit" class="btn btn-primary btn-user btn-block">
                                Login
                            </button>
                        </form>

                        <hr>
                        <div class="text-center">
                            <small>&copy;  Copyright © ATTENDANCE UEMF {{ date('Y') }}</small>
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