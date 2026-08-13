{{-- Bottom tab bar, mobile only. Active state is set by app.js from the path. --}}
<nav class="w-bottom-nav" aria-label="Mobile navigation">
    <a href="{{ route('home') }}">
        <i class="bi bi-house" aria-hidden="true"></i>
        <span>Home</span>
    </a>
    <a href="{{ route('website.quizzes') }}">
        <i class="bi bi-patch-question" aria-hidden="true"></i>
        <span>Quizzes</span>
    </a>
    <a href="{{ route('website.current.affairs.today') }}">
        <i class="bi bi-newspaper" aria-hidden="true"></i>
        <span>Today</span>
    </a>
    <a href="{{ route('website.leaderboard') }}">
        <i class="bi bi-trophy" aria-hidden="true"></i>
        <span>Ranks</span>
    </a>
    @auth
        <a href="{{ route('website.profile.index') }}">
            <i class="bi bi-person" aria-hidden="true"></i>
            <span>Profile</span>
        </a>
    @else
        <a href="{{ route('user.login') }}">
            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
            <span>Login</span>
        </a>
    @endauth
</nav>
