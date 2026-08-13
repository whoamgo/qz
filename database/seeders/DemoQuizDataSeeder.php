<?php

namespace Database\Seeders;

use App\Models\BankOption;
use App\Models\BankQuestion;
use App\Models\Quiz;
use App\Models\QuizXpSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoQuizDataSeeder extends Seeder {
    public function run() {
        foreach ($this->quizzes() as $data) {
            DB::transaction(function () use ($data) {
                $quiz = $this->upsertQuiz($data);

                foreach ($data['questions'] as $index => $questionData) {
                    $question = $this->upsertQuestion($questionData, $data['category_id'], $data['sub_category_id']);
                    $this->attachToQuiz($quiz, $question, $index + 1);
                }

                $quiz->total_questions = $quiz->questions()->count();
                $quiz->save();
            });
        }
    }

    private function upsertQuiz(array $data): Quiz {
        $quiz = Quiz::withTrashed()->firstOrNew(['slug' => $data['slug']]);

        if ($quiz->trashed()) {
            $quiz->restore();
        }

        $quiz->fill([
            'title'                => $data['title'],
            'description'          => $data['description'],
            'category_id'          => $data['category_id'],
            'sub_category_id'      => $data['sub_category_id'],
            'quiz_type'            => Quiz::TYPE_FREE,
            'price'                => 0,
            'difficulty'           => $data['difficulty'],
            'time_limit'           => 30,
            'pass_percentage'      => 60,
            'marks_per_correct'    => 5,
            'negative_marking'     => 0,
            'randomize_questions'  => false,
            'randomize_options'    => false,
            'show_result'          => true,
            'show_correct_answers' => true,
            'show_explanation'     => true,
            'status'               => Quiz::STATUS_PUBLISHED,
        ]);
        $quiz->save();

        $xp = QuizXpSetting::firstOrNew(['quiz_id' => $quiz->id]);
        $xp->fill(['xp_enabled' => true, 'use_global_rules' => true]);
        $xp->save();

        return $quiz;
    }

    private function upsertQuestion(array $data, ?int $categoryId, ?int $subCategoryId): BankQuestion {
        $question = BankQuestion::withTrashed()->firstOrNew([
            'question_text' => $data['question_text'],
            'category_id'   => $categoryId,
        ]);

        if ($question->trashed()) {
            $question->restore();
        }

        $question->fill([
            'sub_category_id' => $subCategoryId,
            'question_type'   => BankQuestion::TYPE_MCQ_SINGLE,
            'difficulty'      => $data['difficulty'],
            'explanation'     => $data['explanation'],
            'default_marks'   => 5,
            'status'          => true,
        ]);
        $question->save();

        $question->options()->delete();

        $correctOptionId = null;
        foreach ($data['options'] as $sortOrder => [$text, $isCorrect]) {
            $option = BankOption::create([
                'bank_question_id' => $question->id,
                'option_text'      => $text,
                'is_correct'       => $isCorrect,
                'sort_order'       => $sortOrder,
            ]);

            if ($isCorrect) {
                $correctOptionId = $option->id;
            }
        }

        $question->correct_option_id = $correctOptionId;
        $question->save();

        return $question;
    }

    private function attachToQuiz(Quiz $quiz, BankQuestion $question, int $order): void {
        DB::table('quiz_bank_question')->updateOrInsert(
            ['quiz_id' => $quiz->id, 'bank_question_id' => $question->id],
            ['question_order' => $order, 'marks' => 5, 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function quizzes(): array {
        return [
            [
                'title'           => 'Current Affairs Essentials',
                'slug'            => 'demo-current-affairs-essentials',
                'description'     => 'Core global-affairs facts that recur across competitive exams.',
                'category_id'     => 1,
                'sub_category_id' => 2,
                'difficulty'      => 'medium',
                'questions'       => [
                    [
                        'question_text' => 'Which country hosted the G20 Leaders\' Summit in September 2023?',
                        'explanation'   => 'India held the G20 presidency in 2023 and hosted the Leaders\' Summit in New Delhi on 9-10 September 2023. The African Union was admitted as a permanent member at this summit.',
                        'difficulty'    => 'easy',
                        'options'       => [['India', true], ['Indonesia', false], ['Brazil', false], ['South Africa', false]],
                    ],
                    [
                        'question_text' => 'World Environment Day is observed every year on which date?',
                        'explanation'   => 'World Environment Day is observed on 5 June, established by the UN General Assembly in 1972 following the Stockholm Conference on the Human Environment.',
                        'difficulty'    => 'easy',
                        'options'       => [['22 April', false], ['5 June', true], ['16 September', false], ['11 December', false]],
                    ],
                    [
                        'question_text' => 'Which institution publishes the World Economic Outlook report?',
                        'explanation'   => 'The International Monetary Fund (IMF) publishes the World Economic Outlook, usually twice a year in April and October, with interim updates in January and July.',
                        'difficulty'    => 'medium',
                        'options'       => [['World Bank', false], ['International Monetary Fund', true], ['World Trade Organization', false], ['UNCTAD', false]],
                    ],
                    [
                        'question_text' => 'The Paris Agreement on climate change was adopted at which Conference of the Parties (COP) session?',
                        'explanation'   => 'The Paris Agreement was adopted at COP21 in Paris in December 2015 and entered into force on 4 November 2016.',
                        'difficulty'    => 'medium',
                        'options'       => [['COP15', false], ['COP18', false], ['COP21', true], ['COP26', false]],
                    ],
                    [
                        'question_text' => 'Under the Paris Agreement, countries agreed to hold the global average temperature rise to well below how many degrees Celsius above pre-industrial levels?',
                        'explanation'   => 'Article 2 of the Paris Agreement commits parties to holding warming to well below 2 degrees Celsius above pre-industrial levels, while pursuing efforts to limit the increase to 1.5 degrees Celsius.',
                        'difficulty'    => 'hard',
                        'options'       => [['1 degree Celsius', false], ['1.5 degrees Celsius', false], ['2 degrees Celsius', true], ['3 degrees Celsius', false]],
                    ],
                ],
            ],
            [
                'title'           => 'Indian History Fundamentals',
                'slug'            => 'demo-indian-history-fundamentals',
                'description'     => 'Ancient, medieval and modern Indian history in one short set.',
                'category_id'     => 20,
                'sub_category_id' => 21,
                'difficulty'      => 'medium',
                'questions'       => [
                    [
                        'question_text' => 'Who was the first President of independent India?',
                        'explanation'   => 'Dr. Rajendra Prasad took office as India\'s first President on 26 January 1950, when the Constitution came into effect. He remains the only President to serve two full terms.',
                        'difficulty'    => 'easy',
                        'options'       => [['Dr. Rajendra Prasad', true], ['Jawaharlal Nehru', false], ['Sardar Vallabhbhai Patel', false], ['Dr. S. Radhakrishnan', false]],
                    ],
                    [
                        'question_text' => 'In which year did India gain independence from British rule?',
                        'explanation'   => 'India became independent on 15 August 1947 under the Indian Independence Act 1947, which also partitioned British India into India and Pakistan.',
                        'difficulty'    => 'easy',
                        'options'       => [['1942', false], ['1945', false], ['1947', true], ['1950', false]],
                    ],
                    [
                        'question_text' => 'Which Mughal emperor commissioned the Taj Mahal?',
                        'explanation'   => 'Shah Jahan commissioned the Taj Mahal at Agra in 1632 as a mausoleum for his wife Mumtaz Mahal. It was designated a UNESCO World Heritage Site in 1983.',
                        'difficulty'    => 'easy',
                        'options'       => [['Akbar', false], ['Jahangir', false], ['Shah Jahan', true], ['Aurangzeb', false]],
                    ],
                    [
                        'question_text' => 'Who founded the Mauryan Empire?',
                        'explanation'   => 'Chandragupta Maurya founded the Mauryan Empire around 322 BCE, overthrowing the Nanda dynasty with guidance from his adviser Chanakya (Kautilya). Bindusara and Ashoka were his successors.',
                        'difficulty'    => 'medium',
                        'options'       => [['Bimbisara', false], ['Chandragupta Maurya', true], ['Bindusara', false], ['Ashoka', false]],
                    ],
                    [
                        'question_text' => 'Which of the four Vedas is the oldest?',
                        'explanation'   => 'The Rigveda is the oldest of the four Vedas, composed in early Vedic Sanskrit. It is a collection of 1,028 hymns arranged into ten books called mandalas.',
                        'difficulty'    => 'hard',
                        'options'       => [['Rigveda', true], ['Samaveda', false], ['Yajurveda', false], ['Atharvaveda', false]],
                    ],
                ],
            ],
            [
                'title'           => 'Cricket Knowledge Test',
                'slug'            => 'demo-cricket-knowledge-test',
                'description'     => 'Records, rules and history from international cricket.',
                'category_id'     => 155,
                'sub_category_id' => 156,
                'difficulty'      => 'medium',
                'questions'       => [
                    [
                        'question_text' => 'Which team won the ICC Cricket World Cup in 2019?',
                        'explanation'   => 'England won their first Cricket World Cup in 2019, beating New Zealand at Lord\'s. The final was tied and decided by a Super Over that also finished level, with England winning on the boundary-count rule since removed.',
                        'difficulty'    => 'easy',
                        'options'       => [['India', false], ['England', true], ['Australia', false], ['New Zealand', false]],
                    ],
                    [
                        'question_text' => 'In cricket, what does the dismissal abbreviation "LBW" stand for?',
                        'explanation'   => 'LBW stands for Leg Before Wicket. A batter may be given out LBW when the ball would have hit the stumps but was intercepted by the batter\'s body, subject to conditions on pitch and impact.',
                        'difficulty'    => 'easy',
                        'options'       => [['Leg Behind Wicket', false], ['Leg Before Wicket', true], ['Long Ball Wide', false], ['Line Beyond Wicket', false]],
                    ],
                    [
                        'question_text' => 'Over how many days is a standard Test match scheduled?',
                        'explanation'   => 'A Test match is scheduled over five days, with each side normally batting twice. Matches often finish early once a result is reached.',
                        'difficulty'    => 'easy',
                        'options'       => [['Three days', false], ['Four days', false], ['Five days', true], ['Six days', false]],
                    ],
                    [
                        'question_text' => 'Who is the leading run-scorer in international cricket across all formats?',
                        'explanation'   => 'Sachin Tendulkar scored 34,357 runs across Tests, ODIs and T20Is. He is also the only player with 100 international centuries.',
                        'difficulty'    => 'medium',
                        'options'       => [['Ricky Ponting', false], ['Kumar Sangakkara', false], ['Sachin Tendulkar', true], ['Jacques Kallis', false]],
                    ],
                    [
                        'question_text' => 'Which batter holds the record for the fastest century in One Day International cricket?',
                        'explanation'   => 'AB de Villiers reached a century off just 31 balls for South Africa against the West Indies at Johannesburg in January 2015, beating Corey Anderson\'s 36-ball record.',
                        'difficulty'    => 'hard',
                        'options'       => [['Shahid Afridi', false], ['Chris Gayle', false], ['Corey Anderson', false], ['AB de Villiers', true]],
                    ],
                ],
            ],
            [
                'title'           => 'Programming Concepts Test',
                'slug'            => 'demo-programming-concepts-test',
                'description'     => 'Data structures, complexity analysis and object-oriented design.',
                'category_id'     => 170,
                'sub_category_id' => 179,
                'difficulty'      => 'hard',
                'questions'       => [
                    [
                        'question_text' => 'Which data structure follows the First In, First Out (FIFO) principle?',
                        'explanation'   => 'A queue is FIFO: elements are enqueued at the rear and dequeued from the front. A stack, by contrast, is Last In, First Out.',
                        'difficulty'    => 'easy',
                        'options'       => [['Stack', false], ['Queue', true], ['Binary tree', false], ['Hash table', false]],
                    ],
                    [
                        'question_text' => 'What is the worst-case time complexity of linear search on an unsorted array of n elements?',
                        'explanation'   => 'Linear search inspects each element in turn, so in the worst case it examines all n elements, giving O(n). Binary search achieves O(log n) but requires sorted input.',
                        'difficulty'    => 'medium',
                        'options'       => [['O(1)', false], ['O(log n)', false], ['O(n)', true], ['O(n squared)', false]],
                    ],
                    [
                        'question_text' => 'In object-oriented programming, what does polymorphism refer to?',
                        'explanation'   => 'Polymorphism lets a single interface operate on values of different types, so the same call resolves to different implementations depending on the object\'s actual type. Hiding internal state is encapsulation, not polymorphism.',
                        'difficulty'    => 'medium',
                        'options'       => [['Hiding an object\'s internal state from callers', false], ['One interface resolving to different implementations by type', true], ['Deriving a class from a parent class', false], ['Restricting a class to a single instance', false]],
                    ],
                    [
                        'question_text' => 'Which of these sorting algorithms has O(n log n) average-case time complexity?',
                        'explanation'   => 'Merge sort divides the input in half and merges sorted halves, giving O(n log n) in all cases. Bubble, insertion and selection sort are all O(n squared) on average.',
                        'difficulty'    => 'hard',
                        'options'       => [['Bubble sort', false], ['Insertion sort', false], ['Merge sort', true], ['Selection sort', false]],
                    ],
                    [
                        'question_text' => 'What is the primary purpose of a hash function in a hash table?',
                        'explanation'   => 'A hash function maps a key to a fixed-size index into the table\'s bucket array, giving average O(1) lookup. Collisions, where two keys map to the same bucket, are handled by chaining or open addressing.',
                        'difficulty'    => 'hard',
                        'options'       => [['To sort keys into ascending order', false], ['To encrypt keys so they cannot be read', false], ['To map a key to a fixed-size bucket index', true], ['To generate cryptographically random values', false]],
                    ],
                ],
            ],
            [
                'title'           => 'Bollywood Cinema Quiz',
                'slug'            => 'demo-bollywood-cinema-quiz',
                'description'     => 'Landmark films, directors and awards from Indian cinema.',
                'category_id'     => 185,
                'sub_category_id' => 186,
                'difficulty'      => 'medium',
                'questions'       => [
                    [
                        'question_text' => 'Which award is India\'s highest honour in cinema, given for lifetime contribution to Indian film?',
                        'explanation'   => 'The Dadasaheb Phalke Award, instituted in 1969, is India\'s highest cinematic honour for lifetime contribution. Devika Rani was its first recipient.',
                        'difficulty'    => 'easy',
                        'options'       => [['Filmfare Lifetime Award', false], ['Dadasaheb Phalke Award', true], ['National Film Award for Best Actor', false], ['Padma Shri', false]],
                    ],
                    [
                        'question_text' => 'Who directed the 1975 blockbuster "Sholay"?',
                        'explanation'   => 'Ramesh Sippy directed Sholay, written by the Salim-Javed duo. It ran for over five years at Mumbai\'s Minerva theatre and is regularly ranked among the greatest Indian films.',
                        'difficulty'    => 'medium',
                        'options'       => [['Yash Chopra', false], ['Ramesh Sippy', true], ['Manmohan Desai', false], ['Raj Kapoor', false]],
                    ],
                    [
                        'question_text' => 'Which Indian composer won two Academy Awards for the "Slumdog Millionaire" score and song in 2009?',
                        'explanation'   => 'A. R. Rahman won the Academy Awards for Best Original Score and, with lyricist Gulzar, Best Original Song for "Jai Ho" at the 81st Academy Awards.',
                        'difficulty'    => 'medium',
                        'options'       => [['Ilaiyaraaja', false], ['Shankar Mahadevan', false], ['A. R. Rahman', true], ['Amit Trivedi', false]],
                    ],
                    [
                        'question_text' => 'In which year was "Raja Harishchandra", regarded as India\'s first full-length feature film, released?',
                        'explanation'   => 'Dadasaheb Phalke\'s Raja Harishchandra was released in 1913 and is regarded as India\'s first full-length feature film, which is why Phalke is called the father of Indian cinema.',
                        'difficulty'    => 'hard',
                        'options'       => [['1899', false], ['1913', true], ['1921', false], ['1931', false]],
                    ],
                    [
                        'question_text' => 'Which was the first Indian film nominated for the Academy Award for Best Foreign Language Film?',
                        'explanation'   => 'Mehboob Khan\'s Mother India (1957) was the first Indian film nominated in that category, losing by a single vote. Salaam Bombay! and Lagaan later received nominations as well.',
                        'difficulty'    => 'hard',
                        'options'       => [['Mother India', true], ['Salaam Bombay!', false], ['Lagaan', false], ['Pather Panchali', false]],
                    ],
                ],
            ],
        ];
    }
}
