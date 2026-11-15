<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MarketplaceDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var list<array{name: string, email: string, student_id: string, course_major: string, year_level: string, campus_address: string, bio: string, services: list<array{title: string, category: string, description: string, price?: string|null, price_negotiable?: bool}>}> $people */
        $people = [
            [
                'name' => 'Amara Santos', 'email' => 'amara.santos@campusconnect.test', 'student_id' => 'CC26-0001',
                'course_major' => 'Psychology', 'year_level' => '3rd year', 'campus_address' => 'Main Campus, Quezon City',
                'bio' => 'Psychology student who enjoys helping classmates build steady study habits and make sense of introductory research methods.',
                'services' => [
                    ['title' => 'Introductory Psychology Tutoring', 'category' => 'Academic', 'description' => 'One-on-one review of core psychology concepts, lecture notes, and practice questions for first- and second-year students.', 'price' => '180.00'],
                    ['title' => 'APA Reference Formatting Help', 'category' => 'Academic', 'description' => 'I can check citation style, reference lists, and document formatting so your own research is presented clearly.', 'price' => '120.00'],
                    ['title' => 'Personalized Study Plan Setup', 'category' => 'Personal', 'description' => 'A practical weekly study schedule built around your class timetable, deadlines, and preferred study blocks.', 'price' => '100.00', 'price_negotiable' => true],
                ],
            ],
            [
                'name' => 'Miguel Reyes', 'email' => 'miguel.reyes@campusconnect.test', 'student_id' => 'CC26-0002',
                'course_major' => 'Civil Engineering', 'year_level' => '2nd year', 'campus_address' => 'Engineering Building, Main Campus',
                'bio' => 'Civil engineering student comfortable with foundational math, technical drawing software, and tidy spreadsheet templates.',
                'services' => [
                    ['title' => 'Algebra and Trigonometry Coaching', 'category' => 'Academic', 'description' => 'Patient problem-solving sessions covering algebra, trigonometry, and the math foundations used in engineering courses.', 'price' => '160.00'],
                    ['title' => 'AutoCAD Student Drawing Support', 'category' => 'Tech', 'description' => 'Help with layers, dimensions, and clean sheet setup for class exercises. Coursework remains your own and is not professional engineering advice.', 'price' => '250.00', 'price_negotiable' => true],
                    ['title' => 'Project Cost Spreadsheet Setup', 'category' => 'Tech', 'description' => 'A simple spreadsheet template with organized quantities, unit costs, and totals for a student project or club activity.', 'price' => '140.00'],
                ],
            ],
            [
                'name' => 'Lian Cruz', 'email' => 'lian.cruz@campusconnect.test', 'student_id' => 'CC26-0003',
                'course_major' => 'Computer Science', 'year_level' => '3rd year', 'campus_address' => 'Innovation Lab, North Campus',
                'bio' => 'Computer science student who likes making approachable web pages and explaining programming fundamentals without jargon.',
                'services' => [
                    ['title' => 'Student Portfolio Website Setup', 'category' => 'Tech', 'description' => 'I can help you set up a simple responsive portfolio page and organize your own project descriptions and images.', 'price' => '450.00'],
                    ['title' => 'Python Basics Study Sessions', 'category' => 'Academic', 'description' => 'Guided practice with variables, loops, functions, and debugging small beginner exercises together.', 'price' => '200.00'],
                    ['title' => 'Laptop Software Setup', 'category' => 'Tech', 'description' => 'Help installing common school and productivity software, organizing folders, and checking basic system settings.', 'price' => '180.00', 'price_negotiable' => true],
                ],
            ],
            [
                'name' => 'Sofia Mendoza', 'email' => 'sofia.mendoza@campusconnect.test', 'student_id' => 'CC26-0004',
                'course_major' => 'Communication Arts', 'year_level' => '4th year', 'campus_address' => 'Media Center, Main Campus',
                'bio' => 'Communication Arts senior with experience in campus events, student publications, and peer presentation practice.',
                'services' => [
                    ['title' => 'Academic Essay Proofreading', 'category' => 'Academic', 'description' => 'A careful grammar and clarity review for up to 1,000 words, with suggestions that keep your voice and ideas intact.', 'price' => '150.00'],
                    ['title' => 'Small Campus Event Photography', 'category' => 'Personal', 'description' => 'Natural photos for club meetings, small performances, and student events. Includes a curated set of edited digital images.', 'price' => '500.00'],
                    ['title' => 'Public Speaking Practice', 'category' => 'Academic', 'description' => 'Low-pressure practice for class presentations, including timing, structure, and clear delivery.', 'price' => '180.00', 'price_negotiable' => true],
                ],
            ],
            [
                'name' => 'Paolo Garcia', 'email' => 'paolo.garcia@campusconnect.test', 'student_id' => 'CC26-0005',
                'course_major' => 'Accountancy', 'year_level' => '3rd year', 'campus_address' => 'Business Hall, East Campus',
                'bio' => 'Accountancy student who enjoys building useful budgets, reviewing introductory accounting, and helping friends prepare for internships.',
                'services' => [
                    ['title' => 'Accounting Fundamentals Tutoring', 'category' => 'Academic', 'description' => 'Review sessions on journal entries, basic financial statements, and introductory accounting exercises.', 'price' => '190.00'],
                    ['title' => 'Personal Budget Spreadsheet', 'category' => 'Tech', 'description' => 'A customized monthly budget sheet with expense categories, automatic totals, and an easy-to-read summary.', 'price' => '160.00'],
                    ['title' => 'Internship Resume Review', 'category' => 'Personal', 'description' => 'Feedback on layout, wording, and readability for a student resume. You keep control of all final edits.', 'price' => '130.00', 'price_negotiable' => true],
                ],
            ],
            [
                'name' => 'Beatriz Navarro', 'email' => 'beatriz.navarro@campusconnect.test', 'student_id' => 'CC26-0006',
                'course_major' => 'Fine Arts', 'year_level' => '2nd year', 'campus_address' => 'Arts Studio, West Campus',
                'bio' => 'Fine Arts student working in watercolor and digital illustration, with a soft spot for handmade campus event posters.',
                'services' => [
                    ['title' => 'Illustrated Club Event Poster', 'category' => 'Personal', 'description' => 'A custom digital poster concept for a student organization event, supplied in social-friendly image sizes.', 'price' => '350.00'],
                    ['title' => 'Watercolor Portrait Commission', 'category' => 'Personal', 'description' => 'A small watercolor portrait based on one clear reference photo, with a scan of the finished artwork.', 'price' => '500.00', 'price_negotiable' => true],
                    ['title' => 'Digital Art Portfolio Feedback', 'category' => 'Academic', 'description' => 'Constructive peer feedback on portfolio order, captions, and how clearly your selected pieces show your range.', 'price' => '150.00'],
                ],
            ],
            [
                'name' => 'Nicolas Villanueva', 'email' => 'nicolas.villanueva@campusconnect.test', 'student_id' => 'CC26-0007',
                'course_major' => 'Information Technology', 'year_level' => '4th year', 'campus_address' => 'Technology Center, North Campus',
                'bio' => 'IT senior who helps classmates get comfortable with everyday software, clear slide decks, and beginner-friendly coding.',
                'services' => [
                    ['title' => 'Everyday Laptop Setup Help', 'category' => 'Tech', 'description' => 'Basic setup for school accounts, browser tools, cloud folders, and common productivity apps on your own laptop.', 'price' => '200.00'],
                    ['title' => 'Clean and Readable Presentation Slides', 'category' => 'Tech', 'description' => 'Formatting help for a class or club presentation, including consistent spacing, readable type, and aligned visuals.', 'price' => '220.00', 'price_negotiable' => true],
                    ['title' => 'HTML and Scratch Starter Tutoring', 'category' => 'Academic', 'description' => 'A friendly introduction to simple web pages or Scratch projects through small exercises you can build yourself.', 'price' => '170.00'],
                ],
            ],
            [
                'name' => 'Camille Dela Rosa', 'email' => 'camille.delarosa@campusconnect.test', 'student_id' => 'CC26-0008',
                'course_major' => 'Nursing', 'year_level' => '3rd year', 'campus_address' => 'Health Sciences Building, Main Campus',
                'bio' => 'Nursing student who makes organized study aids and enjoys helping classmates review course material in manageable pieces.',
                'services' => [
                    ['title' => 'Anatomy Flashcard Study Set', 'category' => 'Academic', 'description' => 'A neat set of original digital flashcards based on your provided lecture topics for personal study and review.', 'price' => '140.00'],
                    ['title' => 'Nursing Fundamentals Study Session', 'category' => 'Academic', 'description' => 'Peer study support for course concepts and lecture notes. This is educational support, not clinical or medical advice.', 'price' => '190.00'],
                    ['title' => 'Lecture Audio Transcription', 'category' => 'Personal', 'description' => 'A readable transcript of a clear recording up to 30 minutes, with speaker notes where the audio is easy to follow.', 'price' => '150.00', 'price_negotiable' => true],
                ],
            ],
            [
                'name' => 'Ethan Lim', 'email' => 'ethan.lim@campusconnect.test', 'student_id' => 'CC26-0009',
                'course_major' => 'Business Administration', 'year_level' => '2nd year', 'campus_address' => 'Business Hall, East Campus',
                'bio' => 'Business student interested in campus entrepreneurship, simple content planning, and making spreadsheets less intimidating.',
                'services' => [
                    ['title' => 'Student Organization Content Calendar', 'category' => 'Tech', 'description' => 'A four-week social content calendar with draft prompts, post dates, and a simple tracking sheet for your club.', 'price' => '250.00'],
                    ['title' => 'Excel Basics Tutoring', 'category' => 'Academic', 'description' => 'Hands-on practice with formulas, sorting, filters, and charts using examples from your own classwork.', 'price' => '170.00', 'price_negotiable' => true],
                    ['title' => 'Product Photos for Student Sellers', 'category' => 'Personal', 'description' => 'A small set of bright, clean product photos for a student-run shop, shot with a simple tabletop setup.', 'price' => '300.00'],
                ],
            ],
            [
                'name' => 'Rina Bautista', 'email' => 'rina.bautista@campusconnect.test', 'student_id' => 'CC26-0010',
                'course_major' => 'English Language Studies', 'year_level' => '3rd year', 'campus_address' => 'Humanities Building, South Campus',
                'bio' => 'English Language Studies student who loves reading groups, thoughtful writing, and relaxed conversation practice.',
                'services' => [
                    ['title' => 'English Conversation Practice', 'category' => 'Academic', 'description' => 'Friendly conversation sessions to build confidence, vocabulary, and fluency for class or everyday situations.', 'price' => '160.00'],
                    ['title' => 'Proofreading for Class Assignments', 'category' => 'Academic', 'description' => 'Grammar and readability suggestions for up to 1,000 words, without rewriting or changing your argument.', 'price' => '130.00'],
                    ['title' => 'Student Book Club Session', 'category' => 'Personal', 'description' => 'A relaxed, one-hour guided discussion for a small group that wants to share reactions to a chosen short reading.', 'price' => '200.00', 'price_negotiable' => true],
                ],
            ],
            [
                'name' => 'Jiro Tan', 'email' => 'jiro.tan@campusconnect.test', 'student_id' => 'CC26-0011',
                'course_major' => 'Architecture', 'year_level' => '4th year', 'campus_address' => 'Design Annex, West Campus',
                'bio' => 'Architecture student with a focus on clear model presentation, visual boards, and practical software workflows for class projects.',
                'services' => [
                    ['title' => 'SketchUp Model Cleanup', 'category' => 'Tech', 'description' => 'Help organizing groups, labels, and scene views in a student SketchUp model so it is easier to present.', 'price' => '320.00'],
                    ['title' => 'Architecture Presentation Board Layout', 'category' => 'Academic', 'description' => 'Peer support arranging your own drawings and captions into a clear class presentation board. Not professional design approval.', 'price' => '280.00', 'price_negotiable' => true],
                    ['title' => 'Hand Lettering for Campus Posters', 'category' => 'Personal', 'description' => 'Custom hand-lettered titles or short phrases for a club poster, invitation, or student event display.', 'price' => '220.00'],
                ],
            ],
            [
                'name' => 'Mica Flores', 'email' => 'mica.flores@campusconnect.test', 'student_id' => 'CC26-0012',
                'course_major' => 'Elementary Education', 'year_level' => '3rd year', 'campus_address' => 'Teacher Education Center, South Campus',
                'bio' => 'Education student who enjoys making approachable learning materials and tutoring young readers after class.',
                'services' => [
                    ['title' => 'Elementary Reading Tutoring', 'category' => 'Academic', 'description' => 'Patient reading practice for elementary learners, with short activities matched to their current level.', 'price' => '160.00'],
                    ['title' => 'Custom Practice Worksheet Pack', 'category' => 'Academic', 'description' => 'A small printable worksheet pack based on a parent- or tutor-provided topic and age range.', 'price' => '150.00', 'price_negotiable' => true],
                    ['title' => 'Proofreading for Class Reports', 'category' => 'Academic', 'description' => 'A straightforward grammar and formatting check for student reports and reflection papers up to 1,000 words.', 'price' => '120.00'],
                ],
            ],
            [
                'name' => 'Adrian Co', 'email' => 'adrian.co@campusconnect.test', 'student_id' => 'CC26-0013',
                'course_major' => 'Hospitality Management', 'year_level' => '4th year', 'campus_address' => 'Hospitality Center, Main Campus',
                'bio' => 'Hospitality Management senior with experience coordinating student activities and a growing interest in food styling.',
                'services' => [
                    ['title' => 'Small Event Checklist and Run Sheet', 'category' => 'Personal', 'description' => 'A practical checklist and event-day schedule for a student organization gathering or small campus celebration.', 'price' => '260.00'],
                    ['title' => 'Mini Dessert Box for Study Groups', 'category' => 'Personal', 'description' => 'A small assorted dessert box prepared for campus pickup with advance notice. Ingredients can be discussed before ordering.', 'price' => '380.00', 'price_negotiable' => true],
                    ['title' => 'Hospitality Case Study Study Group', 'category' => 'Academic', 'description' => 'A guided peer discussion to help organize course concepts and prepare for hospitality case study presentations.', 'price' => '170.00'],
                ],
            ],
            [
                'name' => 'Ysabel Aquino', 'email' => 'ysabel.aquino@campusconnect.test', 'student_id' => 'CC26-0014',
                'course_major' => 'Biology', 'year_level' => '2nd year', 'campus_address' => 'Science Hall, North Campus',
                'bio' => 'Biology student who likes making visual study aids, reviewing foundational topics, and caring for indoor plants.',
                'services' => [
                    ['title' => 'General Biology Tutoring', 'category' => 'Academic', 'description' => 'Peer tutoring for foundational biology topics using your course notes, diagrams, and practice questions.', 'price' => '170.00'],
                    ['title' => 'Custom Biology Flashcards', 'category' => 'Academic', 'description' => 'A concise digital flashcard set organized around a lecture or chapter topic you provide.', 'price' => '110.00', 'price_negotiable' => true],
                    ['title' => 'Indoor Plant Care Visit', 'category' => 'Personal', 'description' => 'A quick campus-area visit to water and check your indoor plants while you are away for a weekend.', 'price' => '180.00'],
                ],
            ],
            [
                'name' => 'Tomas Villareal', 'email' => 'tomas.villareal@campusconnect.test', 'student_id' => 'CC26-0015',
                'course_major' => 'Music', 'year_level' => '3rd year', 'campus_address' => 'Performing Arts Center, South Campus',
                'bio' => 'Music student and weekend guitarist who enjoys helping beginners find a comfortable pace for practice.',
                'services' => [
                    ['title' => 'Beginner Guitar Lessons', 'category' => 'Personal', 'description' => 'Beginner-friendly guitar sessions covering tuning, simple chords, rhythm, and a practice routine you can keep up with.', 'price' => '220.00'],
                    ['title' => 'Piano Practice Coaching', 'category' => 'Academic', 'description' => 'Support with reading simple music, keeping time, and breaking a challenging practice piece into smaller sections.', 'price' => '240.00', 'price_negotiable' => true],
                    ['title' => 'Basic Audio Cleanup for Student Recordings', 'category' => 'Tech', 'description' => 'Light editing to trim silence, balance volume, and export a clean copy of a student music or podcast recording.', 'price' => '280.00'],
                ],
            ],
        ];

        foreach ($people as $person) {
            $user = User::query()->firstOrCreate(
                ['email' => $person['email']],
                [
                    'full_name' => $person['name'],
                    'student_id' => $person['student_id'],
                    'password' => Hash::make(Str::random(64)),
                ]
            );

            $user->profile()->firstOrCreate([], [
                'campus_address' => $person['campus_address'],
                'course_major' => $person['course_major'],
                'year_level' => $person['year_level'],
                'bio' => $person['bio'],
            ]);

            foreach ($person['services'] as $service) {
                $user->services()->firstOrCreate(
                    ['title' => $service['title']],
                    [
                        'category' => $service['category'],
                        'description' => $service['description'],
                        'price' => $service['price'] ?? null,
                        'price_negotiable' => $service['price_negotiable'] ?? false,
                        'status' => 'Active',
                        'visible_in_dashboard' => true,
                    ]
                );
            }
        }
    }
}
