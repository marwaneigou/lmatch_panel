<!DOCTYPE html>
<html lang="en">

<head>
	<title>Panel L'MATCH</title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<!-- Google Fonts: Teko for headers, Inter for body -->
	<link
		href="https://fonts.googleapis.com/css2?family=Teko:wght@400;600;700&family=Inter:wght@400;500;600&display=swap"
		rel="stylesheet">
	<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.6.1/css/all.css">

	<style>
		:root {
			--pitch-green: #10b981;
			/* Emerald 500 */
			--pitch-dark: #064e3b;
			/* Emerald 900 */
			--night-sky: #0f172a;
			/* Slate 900 */
			--glass-bg: rgba(15, 23, 42, 0.85);
			--glass-border: rgba(16, 185, 129, 0.3);
			--text-main: #f8fafc;
			--text-muted: #94a3b8;
		}

		body {
			margin: 0;
			padding: 0;
			display: flex;
			justify-content: center;
			align-items: center;
			min-height: 100vh;
			font-family: 'Inter', sans-serif;
			background: url('/img/stadium-bg.png') no-repeat center center fixed;
			background-size: cover;
			overflow: hidden;
		}

		/* Dark Overlay */
		body::before {
			content: '';
			position: absolute;
			top: 0;
			left: 0;
			right: 0;
			bottom: 0;
			background: rgba(2, 6, 23, 0.6);
			/* Darker overlay */
			backdrop-filter: blur(4px);
			z-index: 0;
		}

		.login-card {
			position: relative;
			z-index: 10;
			width: 100%;
			max-width: 400px;
			background: var(--glass-bg);
			border: 1px solid var(--glass-border);
			border-radius: 16px;
			padding: 2.5rem;
			box-shadow: 0 0 50px rgba(0, 0, 0, 0.8), 0 0 20px rgba(16, 185, 129, 0.2);
			text-align: center;
		}

		.brand-logo {
			margin-bottom: 2rem;
		}

		.brand-logo i {
			font-size: 3rem;
			color: var(--pitch-green);
			filter: drop-shadow(0 0 10px var(--pitch-green));
		}

		.brand-title {
			font-family: 'Teko', sans-serif;
			font-size: 2.5rem;
			font-weight: 700;
			color: var(--text-main);
			text-transform: uppercase;
			letter-spacing: 2px;
			margin: 0;
			line-height: 1;
		}

		.brand-subtitle {
			color: var(--pitch-green);
			font-size: 0.9rem;
			text-transform: uppercase;
			letter-spacing: 4px;
			margin-top: 0.5rem;
			font-weight: 600;
		}

		.form-group {
			margin-bottom: 1.5rem;
			text-align: left;
		}

		.form-label {
			display: block;
			color: var(--text-muted);
			font-size: 0.8rem;
			text-transform: uppercase;
			letter-spacing: 1px;
			margin-bottom: 0.5rem;
			margin-left: 0.5rem;
		}

		.form-control {
			width: 100%;
			padding: 12px 16px;
			/* Explicit padding */
			box-sizing: border-box;
			/* Crucial for preventing width overflow */
			background: rgba(255, 255, 255, 0.05);
			/* Very slight tint */
			border: 1px solid rgba(255, 255, 255, 0.1);
			border-radius: 8px;
			/* Slightly nicer radius */
			color: var(--text-main);
			font-size: 1rem;
			font-family: 'Inter', sans-serif;
			outline: none;
			transition: all 0.3s ease;
		}

		.form-control:focus {
			border-color: var(--pitch-green);
			background: rgba(16, 185, 129, 0.1);
			/* Green tint on focus */
			box-shadow: 0 0 15px rgba(16, 185, 129, 0.2);
		}

		.btn-login {
			width: 100%;
			padding: 14px;
			background: linear-gradient(135deg, var(--pitch-green) 0%, var(--pitch-dark) 100%);
			/* Gradient button */
			border: none;
			border-radius: 8px;
			color: white;
			font-family: 'Teko', sans-serif;
			font-size: 1.5rem;
			text-transform: uppercase;
			letter-spacing: 2px;
			cursor: pointer;
			transition: all 0.3s ease;
			margin-top: 1rem;
		}

		.btn-login:hover {
			transform: translateY(-2px);
			box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
			filter: brightness(1.1);
		}

		.invalid-feedback {
			color: #ef4444;
			/* Red 500 */
			font-size: 0.875rem;
			margin-top: 0.5rem;
			display: block;
		}

		/* ReCaptcha Styling override */
		.g-recaptcha {
			transform: scale(1.0);
			/* Reset scale to normal */
			transform-origin: center;
			margin-bottom: 1rem;
			display: flex;
			justify-content: center;
		}

		/* Ensure the parent container centers it */
		.g-recaptcha>div {
			margin: 0 auto;
		}

		/* Helper links */
		.auth-links {
			margin-top: 1.5rem;
			font-size: 0.875rem;
		}

		.auth-links a {
			color: var(--text-muted);
			text-decoration: none;
			transition: color 0.2s;
		}

		.auth-links a:hover {
			color: var(--pitch-green);
		}
	</style>
</head>

<body>

	<div class="login-card">
		<div class="brand-logo">
			<i class="fas fa-futbol"></i>
			<h1 class="brand-title">Panel L'MATCH</h1>
			<div class="brand-subtitle">Match Day Access</div>
		</div>

		<form method="POST" action="{{ route('login') }}">
			@csrf

			<div class="form-group">
				<label class="form-label" for="name">Manager ID</label>
				<input id="name" type="text" class="form-control" name="name" placeholder="Enter Username"
					value="{{ old('name') }}" required autofocus>
				@error('name')
					<span class="invalid-feedback"><strong>{{ $message }}</strong></span>
				@enderror
			</div>

			<div class="form-group">
				<label class="form-label" for="password">Access Code</label>
				<input id="password" type="password" class="form-control" name="password" placeholder="••••••••"
					required>
				@error('password')
					<span class="invalid-feedback"><strong>{{ $message }}</strong></span>
				@enderror
			</div>

			<div class="form-group"
				style="display: flex; justify-content: center; flex-direction: column; align-items: center;">
				{!! NoCaptcha::renderJs() !!}
				{!! NoCaptcha::display() !!}
				@if($errors->has('g-recaptcha-response'))
					<span class="invalid-feedback">
						<strong>{{$errors->first('g-recaptcha-response')}}</strong>
					</span>
				@endif
			</div>

			<button type="submit" class="btn-login">
				Link Up Play
			</button>

			<div class="auth-links">
				<!-- Optional links -->
			</div>
		</form>
	</div>

</body>

</html>