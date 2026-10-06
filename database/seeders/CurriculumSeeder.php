<?php

namespace Database\Seeders;

use App\Domains\Curriculum\Models\CurriculumItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Assumed South African curriculum data for the demo build.
 * Everything here is editable by administrators once Sprint 12 lands,
 * so the client can correct it without a release.
 */
class CurriculumSeeder extends Seeder
{
    private int $position = 0;

    public function run(): void
    {
        $this->seedSchool();
        $this->seedCollege();
        $this->seedUniversity();
    }

    private function node(string $type, string $name, ?CurriculumItem $parent = null, array $extra = []): CurriculumItem
    {
        return CurriculumItem::create(array_merge([
            'parent_id' => $parent?->id,
            'type' => $type,
            'name' => $name,
            'slug' => Str::slug(($parent?->slug ? $parent->slug.'-' : '').$name),
            'position' => $this->position++,
        ], $extra));
    }

    // ------------------------------------------------------------------ school

    private function seedSchool(): void
    {
        $school = $this->node(CurriculumItem::TYPE_PATHWAY, 'School', null, [
            'icon' => 'school',
            'description' => 'Primary and secondary education, Grade 8 to Grade 12.',
        ]);

        foreach (['Public School' => 'Government-funded schools.', 'Private School' => 'Independent, privately funded schools.'] as $name => $description) {
            $sector = $this->node(CurriculumItem::TYPE_INSTITUTION_TYPE, $name, $school, ['description' => $description]);

            foreach ([8 => 'Eighth Grade', 9 => 'Ninth Grade', 10 => 'Tenth Grade', 11 => 'Eleventh Grade', 12 => 'Twelfth Grade'] as $number => $label) {
                $grade = $this->node(CurriculumItem::TYPE_LEVEL, "Grade {$number}", $sector, [
                    'code' => "GR{$number}",
                    'description' => $label,
                ]);

                foreach ($this->schoolSubjects($number) as $subject => $code) {
                    $this->node(CurriculumItem::TYPE_SUBJECT, $subject, $grade, ['code' => $code]);
                }
            }
        }
    }

    /** CAPS-aligned subject lists. Grades 8–9 are the senior phase; 10–12 are FET. */
    private function schoolSubjects(int $grade): array
    {
        if ($grade <= 9) {
            return [
                'Mathematics' => 'MATH',
                'Natural Sciences' => 'NS',
                'Technology' => 'TECH',
                'Social Sciences' => 'SS',
                'Economic and Management Sciences' => 'EMS',
                'English Home Language' => 'ENGHL',
                'IsiZulu Home Language' => 'ZULHL',
                'Afrikaans First Additional Language' => 'AFRFAL',
                'Life Orientation' => 'LO',
                'Creative Arts' => 'CA',
            ];
        }

        return [
            'Mathematics' => 'MATH',
            'Mathematical Literacy' => 'MATHLIT',
            'Physical Sciences' => 'PHYS',
            'Life Sciences' => 'LIFE',
            'Accounting' => 'ACC',
            'Business Studies' => 'BUS',
            'Economics' => 'ECON',
            'Geography' => 'GEO',
            'History' => 'HIST',
            'English Home Language' => 'ENGHL',
            'IsiZulu Home Language' => 'ZULHL',
            'Afrikaans First Additional Language' => 'AFRFAL',
            'Life Orientation' => 'LO',
            'Information Technology' => 'IT',
            'Computer Applications Technology' => 'CAT',
            'Engineering Graphics and Design' => 'EGD',
            'Tourism' => 'TOUR',
            'Consumer Studies' => 'CONS',
        ];
    }

    // ----------------------------------------------------------------- college

    private function seedCollege(): void
    {
        $college = $this->node(CurriculumItem::TYPE_PATHWAY, 'College', null, [
            'icon' => 'college',
            'description' => 'TVET and private college programmes.',
        ]);

        $public = $this->node(CurriculumItem::TYPE_INSTITUTION_TYPE, 'Public College', $college, [
            'description' => 'Government-funded TVET colleges offering NCV and NATED programmes.',
        ]);

        $ncv = $this->node(CurriculumItem::TYPE_TRACK, 'NCV', $public, [
            'description' => 'National Certificate (Vocational), NQF Levels 2 to 4.',
        ]);

        $ncvProgrammes = [
            'Engineering and Related Design' => ['Mathematics', 'Engineering Practice and Maintenance', 'Engineering Processes', 'Engineering Fundamentals'],
            'Finance, Economics and Accounting' => ['Financial Management', 'Applied Accounting', 'Economic Environment', 'Mathematics'],
            'Information Technology and Computer Science' => ['Introduction to Systems Development', 'Data Communication and Networking', 'Electronics', 'Mathematics'],
            'Electrical Infrastructure Construction' => ['Electrical Principles and Practice', 'Electrical Systems and Construction', 'Mathematics'],
            'Office Administration' => ['Office Practice', 'Business Practice', 'Office Data Processing'],
            'Hospitality' => ['Hospitality Services', 'Food Preparation', 'Client Services and Human Relations'],
        ];

        foreach ($ncvProgrammes as $programme => $modules) {
            $prog = $this->node(CurriculumItem::TYPE_QUALIFICATION, $programme, $ncv);

            foreach (['Level 2', 'Level 3', 'Level 4'] as $levelName) {
                $level = $this->node(CurriculumItem::TYPE_LEVEL, $levelName, $prog);

                foreach ($modules as $module) {
                    $this->node(CurriculumItem::TYPE_SUBJECT, $module, $level);
                }
            }
        }

        $nated = $this->node(CurriculumItem::TYPE_TRACK, 'NATED', $public, [
            'description' => 'National Accredited Technical Education Diploma, N1 to N6.',
        ]);

        $natedProgrammes = [
            'Electrical Engineering' => ['N1', 'N2', 'N3', 'N4', 'N5', 'N6'],
            'Mechanical Engineering' => ['N1', 'N2', 'N3', 'N4', 'N5', 'N6'],
            'Civil Engineering' => ['N1', 'N2', 'N3', 'N4', 'N5', 'N6'],
            'Business Management' => ['N4', 'N5', 'N6'],
            'Financial Management' => ['N4', 'N5', 'N6'],
            'Human Resource Management' => ['N4', 'N5', 'N6'],
            'Educare' => ['N4', 'N5', 'N6'],
        ];

        $natedSubjects = [
            'Electrical Engineering' => ['Mathematics', 'Engineering Science', 'Electrical Trade Theory', 'Industrial Electronics'],
            'Mechanical Engineering' => ['Mathematics', 'Engineering Science', 'Mechanotechnics', 'Fitting and Machining Theory'],
            'Civil Engineering' => ['Mathematics', 'Building Science', 'Building Drawing', 'Quantity Surveying'],
            'Business Management' => ['Entrepreneurship and Business Management', 'Management Communication', 'Computer Practice', 'Financial Accounting'],
            'Financial Management' => ['Financial Accounting', 'Cost and Management Accounting', 'Computerised Financial Systems', 'Economics'],
            'Human Resource Management' => ['Personnel Management', 'Labour Relations', 'Communication', 'Computer Practice'],
            'Educare' => ['Educare Didactics', 'Child Health', 'Day Care Personnel Development', 'Educational Psychology'],
        ];

        foreach ($natedProgrammes as $programme => $levels) {
            $prog = $this->node(CurriculumItem::TYPE_QUALIFICATION, $programme, $nated);

            foreach ($levels as $levelName) {
                $level = $this->node(CurriculumItem::TYPE_LEVEL, $levelName, $prog);

                foreach ($natedSubjects[$programme] as $subject) {
                    $this->node(CurriculumItem::TYPE_SUBJECT, "{$subject} {$levelName}", $level);
                }
            }
        }

        $private = $this->node(CurriculumItem::TYPE_INSTITUTION_TYPE, 'Private College', $college, [
            'description' => 'Independently funded colleges with diverse programmes.',
        ]);

        $privateFaculties = [
            'Business and Commerce' => ['Business Communication', 'Financial Accounting', 'Marketing Management', 'Business Statistics'],
            'Information Technology' => ['Programming Fundamentals', 'Web Development', 'Database Concepts', 'Networking'],
            'Design and Media' => ['Design Principles', 'Digital Illustration', 'Photography', 'Media Studies'],
            'Health and Beauty' => ['Anatomy and Physiology', 'Skincare Therapy', 'Nutrition', 'Business Practice'],
        ];

        foreach (['Year 1', 'Year 2', 'Year 3'] as $yearName) {
            $year = $this->node(CurriculumItem::TYPE_LEVEL, $yearName, $private);

            foreach ($privateFaculties as $faculty => $modules) {
                $fac = $this->node(CurriculumItem::TYPE_FACULTY, $faculty, $year);

                foreach ($modules as $module) {
                    $this->node(CurriculumItem::TYPE_SUBJECT, $module, $fac);
                }
            }
        }
    }

    // -------------------------------------------------------------- university

    private function seedUniversity(): void
    {
        $university = $this->node(CurriculumItem::TYPE_PATHWAY, 'University', null, [
            'icon' => 'university',
            'description' => 'Undergraduate and postgraduate qualifications.',
        ]);

        $faculties = [
            'Engineering' => ['Engineering Mathematics', 'Applied Mechanics', 'Thermodynamics', 'Electrical Systems', 'Engineering Design'],
            'Information Technology' => ['Programming', 'Data Structures and Algorithms', 'Database Systems', 'Software Engineering', 'Networks'],
            'Education' => ['Foundations of Education', 'Educational Psychology', 'Curriculum Studies', 'Teaching Methodology'],
            'Management and Commerce' => ['Financial Accounting', 'Business Management', 'Economics', 'Auditing', 'Taxation'],
            'Art and Design' => ['Design Theory', 'Visual Communication', 'Typography', 'Studio Practice'],
            'Health and Science' => ['Biology', 'Chemistry', 'Physics', 'Human Anatomy', 'Biostatistics'],
            'Law' => ['Introduction to Law', 'Constitutional Law', 'Criminal Law', 'Law of Contract'],
            'Humanities' => ['Academic Literacy', 'Sociology', 'Psychology', 'Political Science'],
        ];

        $qualifications = [
            'Higher Certificate' => ['1st Year'],
            'Diploma' => ['1st Year', '2nd Year', '3rd Year'],
            "Bachelor's Degree" => ['1st Year', '2nd Year', '3rd Year', '4th Year'],
            'Postgraduate' => ['Honours', "Master's", 'Doctoral'],
        ];

        foreach ($qualifications as $qualification => $years) {
            $qual = $this->node(CurriculumItem::TYPE_QUALIFICATION, $qualification, $university);

            foreach ($years as $yearName) {
                $year = $this->node(CurriculumItem::TYPE_LEVEL, $yearName, $qual);

                foreach ($faculties as $faculty => $modules) {
                    $fac = $this->node(CurriculumItem::TYPE_FACULTY, $faculty, $year);

                    foreach ($modules as $module) {
                        $this->node(CurriculumItem::TYPE_SUBJECT, $module, $fac);
                    }
                }
            }
        }
    }
}
