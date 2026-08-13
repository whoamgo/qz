<?php

/*
|--------------------------------------------------------------------------
| Intended Redirect Routes
|--------------------------------------------------------------------------
| App\Lib\Intended reads this map when a guest opens the login or register
| form. The key is the route name the visitor came FROM.
|
|   false            -> send them back to that exact URL after logging in
|   'some.route'     -> send them to that named route instead
|
| Routes not listed here fall through to the default post-login destination.
*/

return [
    'pricing' => false,

    // Public website: after authenticating, drop the visitor back exactly
    // where they were. This is what makes "guest clicks Start Quiz -> login
    // -> lands on the same quiz" work without a parallel redirect system.
    'website.quiz.show'                => false,
    'website.quizzes'                  => false,
    'website.categories'               => false,
    'website.category.show'            => false,
    'website.subcategory.show'         => false,
    'exams'                            => false,
    'website.exam.show'                => false,
    'website.mock.tests'               => false,
    'website.pyq'                      => false,
    'website.current.affairs.index'    => false,
    'website.current.affairs.today'    => false,
    'website.current.affairs.weekly'   => false,
    'website.current.affairs.monthly'  => false,
    'website.leaderboard'              => false,
    'website.search'                   => false,
    'blog'                             => false,
    'blog.details'                     => false,
    'home'                             => false,
];
