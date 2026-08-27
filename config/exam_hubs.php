<?php

/*
|--------------------------------------------------------------------------
| Exam-prep landing hubs
|--------------------------------------------------------------------------
| SEO hubs that CURATE real, already-published quizzes by the subjects each
| exam actually tests. They do not create new taxonomy or thin pages — each
| hub aggregates existing quizzes from the mapped category slugs, and
| ExamHubController noindexes any hub that can't surface enough real quizzes.
|
| 'categories' = parent-category slugs to pull published quizzes from.
| Only slugs that exist are used; unknown slugs are ignored safely.
*/

return [

    // A hub is indexable only if it can show at least this many real quizzes.
    'min_quizzes' => 6,

    'hubs' => [

        'ssc' => [
            'name'        => 'SSC',
            'full_name'   => 'Staff Selection Commission (SSC)',
            'title'       => 'SSC Quiz — Free CGL, CHSL & GD Practice Questions',
            'description' => 'Free SSC practice quizzes on General Knowledge, Reasoning and Current Affairs for CGL, CHSL, MTS and GD — with instant scoring and explanations.',
            'intro'       => 'Staff Selection Commission exams — CGL, CHSL, MTS, GD and Stenographer — reward consistent daily practice across General Awareness, Reasoning and quantitative aptitude. This hub pulls together QuizMitra\'s most relevant SSC-style quizzes so you can drill the exact topics the tiers test, get instant scoring, and read a written explanation after every question.',
            'categories'  => ['general-knowledge', 'reasoning', 'current-affairs', 'competitive-exams', 'business-economy'],
        ],

        'upsc' => [
            'name'        => 'UPSC',
            'full_name'   => 'Union Public Service Commission (UPSC)',
            'title'       => 'UPSC Quiz — Free Prelims GS & Current Affairs Practice',
            'description' => 'Free UPSC-style practice quizzes on General Studies, Current Affairs, History, Polity and Geography for the Civil Services Prelims — with explanations.',
            'intro'       => 'The Civil Services Prelims is won on breadth — General Studies, Current Affairs, History, Polity, Geography and the Economy. This hub curates QuizMitra quizzes that mirror UPSC Prelims patterns, so you can test recall across static GK and dynamic current affairs, then review detailed explanations to close knowledge gaps.',
            'categories'  => ['general-knowledge', 'current-affairs', 'world-quiz', 'competitive-exams', 'business-economy'],
        ],

        'banking' => [
            'name'        => 'Banking',
            'full_name'   => 'Bank PO & Clerk (IBPS / SBI)',
            'title'       => 'Banking Exam Quiz — IBPS & SBI PO/Clerk Practice',
            'description' => 'Free Banking exam quizzes on Reasoning, Current Affairs, Banking Awareness and Computer knowledge for IBPS and SBI PO & Clerk — with instant results.',
            'intro'       => 'IBPS and SBI PO/Clerk selection turns on speed and accuracy across Reasoning, Quantitative Aptitude, Banking & Financial Awareness, Current Affairs and Computer knowledge. This hub gathers QuizMitra quizzes aligned to those sections so you can build sectional speed and track accuracy before the real test.',
            'categories'  => ['reasoning', 'current-affairs', 'business-economy', 'computer-technology', 'competitive-exams'],
        ],

        'railway' => [
            'name'        => 'Railway',
            'full_name'   => 'Railway Recruitment Board (RRB)',
            'title'       => 'Railway Exam Quiz — RRB NTPC & Group D Practice',
            'description' => 'Free Railway (RRB) practice quizzes on General Awareness, Reasoning, Science and Current Affairs for NTPC and Group D — with explanations.',
            'intro'       => 'RRB NTPC and Group D exams test General Awareness, General Intelligence & Reasoning, basic Science and Current Affairs at speed. This hub brings QuizMitra\'s relevant quizzes into one place so you can practise the RRB pattern, build recall, and review why each answer is correct.',
            'categories'  => ['general-knowledge', 'reasoning', 'current-affairs', 'competitive-exams'],
        ],

        'defence' => [
            'name'        => 'Defence',
            'full_name'   => 'Defence Exams (NDA / CDS / AFCAT)',
            'title'       => 'Defence Exam Quiz — NDA, CDS & AFCAT Practice',
            'description' => 'Free Defence exam quizzes on General Knowledge, Current Affairs and General Science for NDA, CDS and AFCAT aspirants — with instant scoring.',
            'intro'       => 'NDA, CDS and AFCAT reward strong General Knowledge, current affairs awareness and general science alongside aptitude. This hub curates QuizMitra quizzes suited to defence-exam preparation, letting you test broad GK, stay current on national and defence affairs, and learn from written explanations.',
            'categories'  => ['general-knowledge', 'current-affairs', 'world-quiz', 'competitive-exams'],
        ],

    ],
];
