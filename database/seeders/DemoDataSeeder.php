<?php

namespace Database\Seeders;

use App\Domains\Curriculum\Models\CurriculumItem;
use App\Domains\Curriculum\Models\Institution;
use App\Domains\Identity\Models\GuardianConsent;
use App\Domains\Tutoring\Models\HelpRequest;
use App\Domains\Tutoring\Models\HelpRequestOffer;
use App\Domains\Tutoring\Models\Message;
use App\Domains\Tutoring\Models\Rating;
use App\Domains\Tutoring\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo accounts and realistic activity for client demonstrations.
 *
 * Kept entirely separate from CurriculumSeeder so that production can load the
 * real curriculum without any demo data. Every account uses the password
 * "password" and must be removed before public launch.
 */
class DemoDataSeeder extends Seeder
{
    private Collection $tutors;

    public function run(): void
    {
        $this->tutors = collect();

        $this->seedInstitutions();
        $admin = $this->seedStaff();
        $this->seedTutors($admin);
        $students = $this->seedStudents();
        $this->seedActivity($students);
        $this->seedReports();
        $this->seedNotifications();
        $this->seedResources();
        $this->seedAnnouncements();
        $this->seedClassrooms();
        $this->seedVoiceNotes();
    }

    private function seedInstitutions(): void
    {
        foreach ([
            ['Durban High School', 'school', 'public'],
            ['Westville Boys High School', 'school', 'public'],
            ['Northwood School', 'school', 'public'],
            ['Clifton College', 'school', 'private'],
            ['Coastal KZN TVET College', 'college', 'public'],
            ['Elangeni TVET College', 'college', 'public'],
            ['Damelin Durban', 'college', 'private'],
            ['University of KwaZulu-Natal', 'university', 'public'],
            ['Durban University of Technology', 'university', 'public'],
            ['Mangosuthu University of Technology', 'university', 'public'],
        ] as [$name, $type, $sector]) {
            Institution::create([
                'name' => $name,
                'type' => $type,
                'sector' => $sector,
                'province' => 'KwaZulu-Natal',
                'city' => 'Durban',
                'is_verified' => true,
            ]);
        }
    }

    private function seedStaff(): User
    {
        $admin = $this->user('Thabo', 'Mkhize', 'admin@dxstudenthelp.co.za', User::ROLE_ADMIN, '1985-04-12');
        $this->user('Nomsa', 'Dlamini', 'moderator@dxstudenthelp.co.za', User::ROLE_MODERATOR, '1990-08-03');

        return $admin;
    }

    /** 20 tutors, weighted towards the launch subjects. */
    private function seedTutors(User $admin): void
    {
        $roster = [
            ['Kgomotso', 'Sithole', 'BSc Mathematics, UKZN', ['Mathematics', 'Mathematical Literacy'], 4.8, 37],
            ['Johan', 'van Wyk', 'BCom Accounting, UNISA', ['Accounting', 'Business Studies'], 4.6, 21],
            ['Ayanda', 'Khumalo', 'BSc Computer Science, UCT', ['Information Technology', 'Programming'], 4.9, 54],
            ['Priya', 'Naidoo', 'BSc Physics, UKZN', ['Physical Sciences', 'Mathematics'], 4.7, 42],
            ['Sibusiso', 'Zulu', 'BEd Mathematics, DUT', ['Mathematics'], 4.5, 18],
            ['Lindiwe', 'Mthembu', 'BSc Life Sciences, UKZN', ['Life Sciences', 'Physical Sciences'], 4.6, 29],
            ['Rajesh', 'Pillay', 'CA(SA), SAICA', ['Accounting', 'Economics'], 4.9, 63],
            ['Nokuthula', 'Ngcobo', 'BA English, Rhodes', ['English Home Language'], 4.4, 15],
            ['Pieter', 'Botha', 'BEd Physical Sciences, NWU', ['Physical Sciences'], null, 0],
            ['Zanele', 'Mahlangu', 'BCom Economics, Wits', ['Economics', 'Business Studies'], 4.3, 12],
            ['Farhan', 'Patel', 'BSc Eng Electrical, UKZN', ['Mathematics', 'Physical Sciences'], 4.8, 33],
            ['Thandeka', 'Mkhwanazi', 'BA IsiZulu, UNIZULU', ['IsiZulu Home Language'], 4.7, 20],
            ['Michael', 'Oosthuizen', 'BSc Geography, UP', ['Geography', 'Life Sciences'], 4.2, 9],
            ['Nelisiwe', 'Shabalala', 'BEd Foundation Phase, DUT', ['Mathematics', 'Natural Sciences'], 4.5, 24],
            ['Devan', 'Govender', 'BSc IT, DUT', ['Information Technology', 'Computer Applications Technology'], 4.6, 27],
            ['Karabo', 'Molefe', 'BCom Financial Management', ['Accounting', 'Mathematical Literacy'], 4.4, 16],
            ['Hendrik', 'Steyn', 'BEng Mechanical, NWU', ['Engineering Science', 'Mathematics'], 4.7, 31],
            ['Busisiwe', 'Nkosi', 'BA History, UKZN', ['History', 'Geography'], 4.1, 7],
            ['Shireen', 'Abrahams', 'BSc Chemistry, UWC', ['Physical Sciences', 'Chemistry'], 4.8, 38],
            ['Mpho', 'Radebe', 'BSc Statistics, Wits', ['Mathematics', 'Business Statistics'], null, 0],
        ];

        foreach ($roster as $index => [$first, $last, $qualification, $subjects, $rating, $resolved]) {
            $email = $index === 0 ? 'tutor@dxstudenthelp.co.za' : 'tutor'.($index + 1).'@dxstudenthelp.co.za';

            // Two tutors are left pending so the verification queue has content.
            $status = $rating === null ? TutorProfile::STATUS_PENDING : TutorProfile::STATUS_APPROVED;

            $user = $this->user($first, $last, $email, User::ROLE_TUTOR, '199'.($index % 10).'-06-15');

            $profile = TutorProfile::create([
                'user_id' => $user->id,
                'bio' => "{$first} helps learners with ".implode(' and ', $subjects).'.',
                'highest_qualification' => $qualification,
                'languages' => ['English', $index % 3 === 0 ? 'isiZulu' : 'Afrikaans'],
                'availability' => ['weekdays' => '16:00-20:00', 'weekends' => '09:00-13:00'],
                'verification_status' => $status,
                'reviewed_by' => $status === TutorProfile::STATUS_APPROVED ? $admin->id : null,
                'reviewed_at' => $status === TutorProfile::STATUS_APPROVED ? now()->subDays(rand(5, 40)) : null,
                'is_available' => $index % 7 !== 0,
                'average_rating' => $rating,
                'ratings_count' => $resolved,
                'resolved_count' => $resolved,
            ]);

            $items = CurriculumItem::ofType(CurriculumItem::TYPE_SUBJECT)
                ->whereIn('name', $subjects)
                ->get();

            $profile->subjects()->sync($items->pluck('id'));
            $user->subjects()->sync($items->pluck('id')->mapWithKeys(fn ($id) => [$id => ['role' => 'subject']]));

            if ($status === TutorProfile::STATUS_APPROVED) {
                $this->tutors->push($user);
            } else {
                // Pending tutors arrive with documents so the queue is reviewable.
                foreach (['id', 'qualification', 'police_clearance'] as $type) {
                    $path = "verification/{$profile->id}/{$type}-sample.pdf";
                    \Illuminate\Support\Facades\Storage::disk('local')->put(
                        $path,
                        "Demo document placeholder: {$type} for {$first} {$last}.",
                    );

                    $profile->documents()->create([
                        'document_type' => $type,
                        'path' => $path,
                        'original_name' => ucfirst(str_replace('_', ' ', $type)).'.pdf',
                        'size' => 182000,
                    ]);
                }
            }
        }
    }

    /** @return Collection<int, User> */
    private function seedStudents(): Collection
    {
        $students = collect();

        $schoolRoster = [
            ['Sipho', 'Ndlovu', 'student@dxstudenthelp.co.za', 'Grade 11', 16, ['Mathematics', 'Physical Sciences', 'Accounting']],
            ['Amahle', 'Cele', 'student3@dxstudenthelp.co.za', 'Grade 12', 17, ['Mathematics', 'Life Sciences', 'English Home Language']],
            ['Bongani', 'Mabaso', 'student4@dxstudenthelp.co.za', 'Grade 10', 15, ['Mathematical Literacy', 'Business Studies']],
            ['Chloe', 'Pretorius', 'student5@dxstudenthelp.co.za', 'Grade 9', 14, ['Mathematics', 'Natural Sciences']],
            ['Thabiso', 'Modise', 'student6@dxstudenthelp.co.za', 'Grade 12', 18, ['Physical Sciences', 'Information Technology']],
            ['Zinhle', 'Buthelezi', 'student7@dxstudenthelp.co.za', 'Grade 11', 16, ['Accounting', 'Economics']],
            ['Yusuf', 'Ismail', 'student8@dxstudenthelp.co.za', 'Grade 10', 15, ['Mathematics', 'Geography']],
            ['Palesa', 'Tshabalala', 'student9@dxstudenthelp.co.za', 'Grade 12', 17, ['IsiZulu Home Language', 'History']],
        ];

        foreach ($schoolRoster as [$first, $last, $email, $grade, $age, $subjects]) {
            $student = $this->user($first, $last, $email, User::ROLE_STUDENT, now()->subYears($age)->format('Y-m-d'));

            if ($student->isMinor()) {
                // One learner is left pending so the restricted state can be demonstrated.
                $this->consent($student, $email === 'student5@dxstudenthelp.co.za'
                    ? GuardianConsent::STATUS_PENDING
                    : GuardianConsent::STATUS_APPROVED);
            }

            $context = CurriculumItem::ofType(CurriculumItem::TYPE_LEVEL)
                ->where('name', $grade)
                ->whereHas('parent', fn ($q) => $q->where('name', 'Public School'))
                ->first();

            $this->place($student, $context, $subjects);
            $students->push($student);
        }

        // University students
        $uniRoster = [
            ['Lerato', 'Mokoena', 'student2@dxstudenthelp.co.za', 'Information Technology', ['Programming', 'Database Systems']],
            ['Andile', 'Dube', 'student10@dxstudenthelp.co.za', 'Engineering', ['Engineering Mathematics', 'Applied Mechanics']],
            ['Michelle', 'Fourie', 'student11@dxstudenthelp.co.za', 'Management and Commerce', ['Financial Accounting', 'Economics']],
        ];

        foreach ($uniRoster as [$first, $last, $email, $faculty, $subjects]) {
            $student = $this->user($first, $last, $email, User::ROLE_STUDENT, now()->subYears(21)->format('Y-m-d'));

            $context = CurriculumItem::ofType(CurriculumItem::TYPE_FACULTY)
                ->where('name', $faculty)
                ->whereHas('parent', fn ($q) => $q->where('name', '1st Year')
                    ->whereHas('parent', fn ($p) => $p->where('name', "Bachelor's Degree")))
                ->first();

            $this->place($student, $context, $subjects);
            $students->push($student);
        }

        // College students
        $collegeRoster = [
            ['Sanele', 'Gumede', 'student12@dxstudenthelp.co.za', 'Engineering and Related Design', 'Level 3'],
            ['Refilwe', 'Sibiya', 'student13@dxstudenthelp.co.za', 'Finance, Economics and Accounting', 'Level 2'],
        ];

        foreach ($collegeRoster as [$first, $last, $email, $programme, $level]) {
            $student = $this->user($first, $last, $email, User::ROLE_STUDENT, now()->subYears(19)->format('Y-m-d'));

            $context = CurriculumItem::ofType(CurriculumItem::TYPE_LEVEL)
                ->where('name', $level)
                ->whereHas('parent', fn ($q) => $q->where('name', $programme))
                ->first();

            $subjects = $context?->children()->ofType(CurriculumItem::TYPE_SUBJECT)->limit(2)->pluck('name')->all() ?? [];

            $this->place($student, $context, $subjects);
            $students->push($student);
        }

        return $students;
    }

    /** Help requests across every status, so each screen has content. */
    private function seedActivity(Collection $students): void
    {
        $questions = [
            ['Factorising trinomials', 'I get stuck when the coefficient of x squared is not 1. Could you walk me through the method on a worked example?'],
            ["Newton's second law problem", 'A 5 kg block on a frictionless surface is pushed with 20 N. I worked out the acceleration but I am not sure how to draw the free-body diagram.'],
            ['Bank reconciliation statement', 'My cash book balance does not agree with the bank statement. How do I treat outstanding cheques and deposits in transit?'],
            ['Balancing chemical equations', 'I can balance simple equations but redox reactions confuse me completely. Where do I start?'],
            ['Essay structure for a discursive essay', 'My teacher says my arguments are not developed enough. How should I structure each body paragraph?'],
            ['Photosynthesis light and dark reactions', 'I keep mixing up what happens in the thylakoid versus the stroma.'],
            ['Trigonometric identities', 'How do I know which identity to use when proving an expression?'],
            ['Supply and demand elasticity', 'I understand the graphs but I cannot interpret what elastic demand means for a real business.'],
            ['Loops in Python', 'My for loop runs one time too many and I cannot see why. Is it an off-by-one error?'],
            ['Interpreting a contour map', 'How do I work out the gradient between two points from the contour lines?'],
            ['Quadratic formula vs completing the square', 'When should I use each method in an exam?'],
            ['Writing a balanced journal entry', 'I am confused about which account is debited when we buy equipment on credit.'],
        ];

        $statuses = [
            HelpRequest::STATUS_CLOSED, HelpRequest::STATUS_CLOSED, HelpRequest::STATUS_CLOSED,
            HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_ASSIGNED, HelpRequest::STATUS_ASSIGNED,
            HelpRequest::STATUS_OPEN, HelpRequest::STATUS_OPEN, HelpRequest::STATUS_ESCALATED,
            HelpRequest::STATUS_CANCELLED,
        ];

        $participating = $students->filter(fn (User $s) => $s->canParticipate())->values();

        foreach (range(0, 44) as $index) {
            $student = $participating[$index % $participating->count()];
            $subject = $student->subjects()->inRandomOrder()->first();

            if (! $subject) {
                continue;
            }

            [$topic, $description] = $questions[$index % count($questions)];
            $status = $statuses[$index % count($statuses)];
            $createdAt = now()->subDays(rand(0, 45))->subHours(rand(0, 23));

            $tutor = $this->tutors
                ->filter(fn (User $t) => $t->tutorProfile->subjects->contains('id', $subject->id))
                ->shuffle()
                ->first();

            $isAssigned = in_array($status, [
                HelpRequest::STATUS_ASSIGNED, HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_CLOSED,
            ], true) && $tutor !== null;

            $request = HelpRequest::create([
                'student_id' => $student->id,
                'tutor_id' => $isAssigned ? $tutor->id : null,
                'subject_id' => $subject->id,
                'topic' => $topic,
                'description' => $description,
                'status' => $isAssigned
                    ? $status
                    : (in_array($status, [HelpRequest::STATUS_ESCALATED, HelpRequest::STATUS_CANCELLED], true)
                        ? $status
                        : HelpRequest::STATUS_OPEN),
                'assigned_at' => $isAssigned ? $createdAt->copy()->addHours(rand(1, 20)) : null,
                'escalated_at' => $status === HelpRequest::STATUS_ESCALATED ? $createdAt->copy()->addDay() : null,
                'resolved_at' => in_array($status, [HelpRequest::STATUS_RESOLVED, HelpRequest::STATUS_CLOSED], true)
                    ? $createdAt->copy()->addHours(rand(21, 40)) : null,
                'closed_at' => $status === HelpRequest::STATUS_CLOSED ? $createdAt->copy()->addHours(rand(41, 60)) : null,
                'last_activity_at' => $createdAt->copy()->addHours(rand(1, 48)),
            ]);

            $request->forceFill(['created_at' => $createdAt])->save();

            // Offer the request to every tutor who covers the subject
            foreach ($this->tutors->filter(fn (User $t) => $t->tutorProfile->subjects->contains('id', $subject->id)) as $eligible) {
                HelpRequestOffer::create([
                    'help_request_id' => $request->id,
                    'tutor_id' => $eligible->id,
                    'status' => $isAssigned && $eligible->id === $tutor->id
                        ? HelpRequestOffer::STATUS_ACCEPTED
                        : HelpRequestOffer::STATUS_OFFERED,
                ]);
            }

            if ($isAssigned) {
                $this->conversation($request, $student, $tutor);
            }

            if ($isAssigned && $status === HelpRequest::STATUS_CLOSED) {
                Rating::create([
                    'help_request_id' => $request->id,
                    'student_id' => $student->id,
                    'tutor_id' => $tutor->id,
                    'stars' => rand(4, 5),
                    'comment' => collect([
                        'Explained it in a way that finally made sense.',
                        'Very patient and gave me extra practice questions.',
                        'Quick reply and clear working out.',
                        null,
                    ])->random(),
                ]);
            }
        }
    }

    /** A couple of open reports so the moderation queue has something to show. */
    private function seedReports(): void
    {
        $moderation = app(\App\Domains\Tutoring\Services\ModerationService::class);

        $messages = Message::with('helpRequest.student')->inRandomOrder()->limit(3)->get();

        $reasons = ['contact_details', 'academic_dishonesty', 'inappropriate'];

        foreach ($messages as $index => $message) {
            $reporter = $message->helpRequest?->student;

            if (! $reporter) {
                continue;
            }

            $report = $moderation->report(
                $reporter,
                $message,
                $reasons[$index % count($reasons)],
                'Reported from the conversation screen during demo data generation.',
            );

            // Leave the newest open; close the oldest so both views have content.
            if ($index === 2) {
                $moderation->resolve(
                    $report,
                    User::where('role', User::ROLE_MODERATOR)->first(),
                    'dismiss',
                    'Reviewed: no action needed, the tutor was explaining method only.',
                );
            }
        }
    }

    /** A few in-app notifications so the bell is not empty in the demo. */
    private function seedNotifications(): void
    {
        $requests = HelpRequest::with(['student', 'tutor', 'subject'])
            ->whereNotNull('tutor_id')
            ->limit(6)
            ->get();

        foreach ($requests as $index => $request) {
            $request->student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => \App\Notifications\RequestAccepted::class,
                'data' => [
                    'event' => 'request_accepted',
                    'title' => 'A tutor has accepted your request',
                    'body' => ($request->tutor->first_name ?? 'A tutor').' picked up your question on "'.$request->topic.'".',
                    'url' => route('conversations.show', $request),
                ],
                'read_at' => $index > 2 ? now()->subDays(1) : null,
                'created_at' => now()->subHours($index * 5 + 1),
                'updated_at' => now()->subHours($index * 5 + 1),
            ]);
        }
    }

    /** Study notes, past papers and solutions so the library is not empty. */
    private function seedResources(): void
    {
        $catalogue = [
            ['Grade 11 Trigonometry — worked examples', 'notes', 'Fifteen worked examples covering identities, proofs and the sine and cosine rules.'],
            ['Grade 12 Mathematics Paper 1 (2025)', 'past_paper', 'Full question paper from the November 2025 NSC examination.'],
            ['Grade 12 Mathematics Paper 1 — memo', 'solution', 'Step-by-step solutions with mark allocations.'],
            ['Physical Sciences: Newton\'s laws summary', 'notes', 'One-page summary with free-body diagram practice.'],
            ['Chemical equations: balancing drill', 'course_material', 'Twenty practice equations, answers at the end.'],
            ['Accounting: bank reconciliation explained', 'notes', 'Worked example from cash book to reconciled balance.'],
            ['Voice note: the chain rule in two minutes', 'other', 'A short spoken explanation you can listen to on the way to school.'],
            ['Life Sciences: photosynthesis diagrams', 'notes', 'Labelled diagrams for the light and dark reactions.'],
            ['English HL: structuring a discursive essay', 'notes', 'Paragraph-by-paragraph structure with a model answer.'],
            ['Grade 11 Accounting June paper', 'past_paper', 'Mid-year examination paper with answer sheet.'],
            ['Economics: elasticity made simple', 'notes', 'Graphs explained in plain language with real South African examples.'],
            ['Geography: reading contour maps', 'course_material', 'How to work out gradient and cross-sections.'],
        ];

        $uploaders = $this->tutors->take(6);
        $admin = User::where('role', User::ROLE_ADMIN)->first();

        foreach ($catalogue as $index => [$title, $type, $description]) {
            $uploader = $index % 4 === 0 ? $admin : $uploaders[$index % $uploaders->count()];
            $subject = $uploader->subjects()->inRandomOrder()->first();

            if (! $subject) {
                continue;
            }

            // Two are left pending so the review queue has content.
            $pending = in_array($index, [10, 11], true) && ! $uploader->isStaff();

            $path = 'resources/'.$uploader->id.'/'.Str::slug($title).'.pdf';
            \Illuminate\Support\Facades\Storage::disk('local')->put($path, "Demo material placeholder: {$title}.");

            $resource = \App\Domains\Content\Models\Resource::create([
                'uploaded_by' => $uploader->id,
                'title' => $title,
                'description' => $description,
                'resource_type' => $type,
                'path' => $path,
                'mime_type' => $type === 'other' ? 'audio/mpeg' : 'application/pdf',
                'size' => rand(120000, 2400000),
                'rights_declared' => true,
                'status' => $pending
                    ? \App\Domains\Content\Models\Resource::STATUS_PENDING
                    : \App\Domains\Content\Models\Resource::STATUS_PUBLISHED,
                'approved_by' => $pending ? null : $admin?->id,
                'approved_at' => $pending ? null : now()->subDays(rand(1, 20)),
                'views' => $pending ? 0 : rand(5, 180),
                'downloads' => $pending ? 0 : rand(2, 90),
            ]);

            $resource->curriculumItems()->sync([$subject->id]);
        }
    }

    private function seedAnnouncements(): void
    {
        $admin = User::where('role', User::ROLE_ADMIN)->first();
        $grade12 = \App\Domains\Curriculum\Models\CurriculumItem::where('type', 'level')
            ->where('name', 'Grade 12')->first();

        $items = [
            ['Welcome to DX Student Help', 'Ask a question in any of your subjects and a verified tutor will help you. All conversations stay on the platform and are monitored to keep you safe.', 'normal', [], []],
            ['November exam timetable is out', 'The final NSC timetable has been released. Check with your school for your exam centre details.', 'important', ['student'], $grade12 ? [$grade12->id] : []],
            ['New past papers added', 'Mathematics and Physical Sciences papers with full memos are now in the study material section.', 'normal', ['student'], []],
            ['Tutors: please keep your availability up to date', 'If you are unavailable for a while, switch availability off so requests go to someone who can answer quickly.', 'normal', ['tutor'], []],
        ];

        foreach ($items as $index => [$title, $body, $priority, $roles, $itemIds]) {
            \App\Domains\Content\Models\Announcement::create([
                'author_id' => $admin?->id,
                'title' => $title,
                'body' => $body,
                'priority' => $priority,
                'targeting' => ['roles' => $roles, 'curriculum_item_ids' => $itemIds],
                'publish_at' => now()->subDays($index * 3 + 1),
            ]);
        }
    }

    /** One own-group class and one school-linked class awaiting approval. */
    private function seedClassrooms(): void
    {
        $service = app(\App\Domains\Classroom\Services\ClassroomService::class);
        $students = User::where('role', User::ROLE_STUDENT)->get();
        $school = \App\Domains\Curriculum\Models\Institution::where('type', 'school')->first();

        foreach ($this->tutors->take(2) as $index => $teacher) {
            $subject = $teacher->subjects()->first();

            $classroom = $service->create($teacher, [
                'name' => $subject ? $subject->name.' revision group' : 'Study group',
                'description' => 'Weekly revision, worked examples and past paper practice.',
                'type' => $index === 1 ? \App\Domains\Classroom\Models\Classroom::TYPE_SCHOOL
                                       : \App\Domains\Classroom\Models\Classroom::TYPE_PERSONAL,
                'institution_id' => $index === 1 ? $school?->id : null,
                'curriculum_item_id' => $subject?->id,
                'capacity' => 40,
            ]);

            foreach ($students->shuffle()->take(5) as $student) {
                if ($student->canParticipate()) {
                    $service->join($classroom, $student);
                }
            }

            $classroom->posts()->create([
                'author_id' => $teacher->id,
                'type' => 'note',
                'title' => 'Welcome to the group',
                'body' => 'Post your questions here during the week and I will work through the hardest ones on Saturday.',
            ]);

            $classroom->posts()->create([
                'author_id' => $teacher->id,
                'type' => 'task',
                'title' => 'Past paper: Section A',
                'body' => 'Attempt Section A of the 2025 paper before our next session. Mark yourself done when finished.',
                'due_at' => now()->addDays(5),
            ]);
        }
    }

    /** Voice note lessons, including one flagged for contact details spoken aloud. */
    private function seedVoiceNotes(): void
    {
        $service = app(\App\Domains\Voice\Services\VoiceNoteService::class);
        $classrooms = \App\Domains\Classroom\Models\Classroom::with('teacher')->get();

        $samples = [
            ['The chain rule in two minutes', 118, 'Start by differentiating the outer function, then multiply by the derivative of the inner one. Let us work through two examples together.'],
            ['Balancing redox equations', 164, 'Split the reaction into half equations first. Balance the atoms, then the charges, and only then combine them again.'],
            ['Bank reconciliation walkthrough', 203, 'Begin with the cash book balance. Add deposits in transit, subtract outstanding cheques, and you should land on the bank statement balance. If you get stuck, call me on 082 123 4567 and we can go through it.'],
        ];

        foreach ($samples as $index => [$title, $seconds, $transcript]) {
            $classroom = $classrooms[$index % max($classrooms->count(), 1)] ?? null;

            if (! $classroom) {
                continue;
            }

            $path = 'voice-notes/'.$classroom->teacher_id.'/'.Str::slug($title).'.webm';
            \Illuminate\Support\Facades\Storage::disk('local')->put($path, 'Demo audio placeholder for '.$title);

            $note = \App\Domains\Voice\Models\VoiceNote::create([
                'user_id' => $classroom->teacher_id,
                'attachable_type' => \App\Domains\Classroom\Models\Classroom::class,
                'attachable_id' => $classroom->id,
                'path' => $path,
                'mime_type' => 'audio/webm',
                'size' => $seconds * 12000,
                'duration_seconds' => $seconds,
                'title' => $title,
                'transcription_status' => \App\Domains\Voice\Models\VoiceNote::STATUS_PENDING,
                'plays' => rand(3, 40),
            ]);

            // Shows the safeguarding pipeline working: the third one is flagged.
            $service->applyTranscript($note, $transcript);
        }
    }

    private function conversation(HelpRequest $request, User $student, User $tutor): void
    {
        $exchange = [
            [$tutor, 'Hi '.$student->first_name.", happy to help. Can you show me what you have tried so far?"],
            [$student, 'I tried the first step but then I got lost. Here is my working.'],
            [$tutor, 'Good start. The step you are missing is in the middle. Try it again and tell me what you get.'],
        ];

        foreach ($exchange as $index => [$sender, $body]) {
            Message::create([
                'help_request_id' => $request->id,
                'sender_id' => $sender->id,
                'body' => $body,
                'body_original' => $body,
                'created_at' => $request->assigned_at?->copy()->addMinutes(($index + 1) * 12),
                'updated_at' => $request->assigned_at?->copy()->addMinutes(($index + 1) * 12),
            ]);
        }
    }

    private function user(string $first, string $last, string $email, string $role, string $dob): User
    {
        return User::create([
            'first_name' => $first,
            'last_name' => $last,
            'name' => "{$first} {$last}",
            'email' => $email,
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'date_of_birth' => $dob,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function consent(User $user, string $status): void
    {
        GuardianConsent::create([
            'user_id' => $user->id,
            'guardian_name' => 'Parent of '.$user->first_name,
            'guardian_email' => 'guardian.'.Str::slug($user->first_name).'@example.co.za',
            'guardian_mobile' => '+27 82 '.rand(100, 999).' '.rand(1000, 9999),
            'token' => Str::random(48),
            'status' => $status,
            'requested_at' => now()->subDays(14),
            'decided_at' => $status === GuardianConsent::STATUS_APPROVED ? now()->subDays(13) : null,
            'policy_version' => '1.0',
        ]);
    }

    private function place(User $user, ?CurriculumItem $context, array $subjectNames): void
    {
        if (! $context) {
            return;
        }

        $contextIds = collect($context->ancestors())->pluck('id')->push($context->id);
        $user->academicContext()->sync($contextIds->mapWithKeys(fn ($id) => [$id => ['role' => 'context']]));

        $subjects = $context->children()
            ->ofType(CurriculumItem::TYPE_SUBJECT)
            ->whereIn('name', $subjectNames)
            ->get();

        if ($subjects->isEmpty()) {
            $subjects = $context->children()->ofType(CurriculumItem::TYPE_SUBJECT)->limit(3)->get();
        }

        $user->subjects()->sync($subjects->pluck('id')->mapWithKeys(fn ($id) => [$id => ['role' => 'subject']]));
        $user->update(['onboarding_completed_at' => now()->subDays(rand(5, 30))]);
    }
}
