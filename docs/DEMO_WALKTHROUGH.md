# DX Student Help — demo walkthrough

Everything below works on the seeded demo data. All accounts use the password
`password`. Reset at any time with `php artisan migrate:fresh --seed`.

## Accounts

| Role | Email |
|---|---|
| Administrator | admin@dxstudenthelp.co.za |
| Moderator | moderator@dxstudenthelp.co.za |
| Student, Grade 11, consent approved | student@dxstudenthelp.co.za |
| Student, Grade 9, consent **pending** | student5@dxstudenthelp.co.za |
| Student, university 1st year | student2@dxstudenthelp.co.za |
| Tutor, verified (Maths, Physical Sciences) | tutor@dxstudenthelp.co.za |
| Tutor, pending verification | tutor9@dxstudenthelp.co.za |

## 1. Registration and guardian consent (5 minutes)

1. Register a new account with a date of birth under 18. The guardian fields
   appear as soon as the date is entered.
2. The guardian receives an approval email. In the demo, read it from the mail
   log (`storage/logs/laravel.log`) or configure a real mail service.
3. Open the approval link: the guardian sees what is collected, why, and how the
   learner is kept safe, then approves or declines.
4. The learner is notified and the account unlocks.

Show the restricted state first by signing in as `student5@dxstudenthelp.co.za`,
whose guardian has not yet approved: they can browse but cannot ask for help.

## 2. Onboarding (3 minutes)

Walk School → Public → Grade 11 → subjects. The tracker at the bottom counts the
selections. Repeat as College (NCV or NATED) and University to show the same
wizard adapting to completely different curriculum shapes, driven by data only.

## 3. The core loop (7 minutes)

1. As `student@dxstudenthelp.co.za`, ask for help. The form shows how many tutors
   cover the subject and the typical response time.
2. As `tutor@dxstudenthelp.co.za`, open the queue and accept it.
3. Message both ways. The student sees the reply arrive without reloading.
4. **Type a phone number** such as "call me on 082 123 4567". It is masked, and
   the sender is told why.
5. Report the message from the student side.
6. Tutor marks it resolved; student confirms and rates.

## 4. Safeguarding (3 minutes)

As `moderator@dxstudenthelp.co.za`:

1. Open the moderation queue. Reports involving a learner under 18 are marked
   high priority and sort first.
2. Open the conversation review: masked and original text side by side.
3. Take an action. The written outcome is required and goes to the audit log.

## 5. Notifications (2 minutes)

The bell in the header polls every 30 seconds. The notifications page lists
everything and lets the user choose in-app and email per event. Account,
security and guardian consent email can never be switched off.

## What is not built yet

Tutor self-onboarding with document upload, the admin console, resource library,
announcements, community, progress, monetisation, payments and the mobile apps.
Those are builds B4 to B11.
