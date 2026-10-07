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

## 5. AI Tutor (6 minutes)

Sign in as `student@dxstudenthelp.co.za` and open **Learn**. Seven published
lessons across seven subjects are waiting:

| Lesson | Subject | Languages |
|---|---|---|
| Factorising trinomials when a is not 1 | Grade 11 Mathematics | English, isiZulu |
| Newton's second law: F = ma in practice | Grade 12 Physical Sciences | English, Afrikaans |
| Compound and double angle identities | Grade 12 Mathematics | English |
| Photosynthesis: the light and dark reactions | Grade 11 Life Sciences | English |
| Bank reconciliation, step by step | Grade 11 Accounting | English |
| Structuring a discursive essay | Grade 12 English Home Language | English |
| Price elasticity of demand | Grade 11 Economics | English |


1. **Pick a language.** English and isiZulu are both published for the Grade 11
   Mathematics topic. Choose isiZulu and open the lesson.
2. **Note what stayed in English.** The explanation is in isiZulu, but `ax² + bx + c`,
   "common factor", "grouping" and the formulas are in English — because the NSC
   paper is written in English.
3. **Work through the lesson.** Eight parts, each with a pause-and-think question.
   Press "Listen to this part" for narration. Jump back to any part.
4. **Open Revision notes and Flashcards.** Same content, three ways in.
5. **Finish and test yourself.** Only Basic is unlocked. Score 80% and Easy opens.
6. **Get one wrong deliberately.** The result names the actual mistake —
   "Using c instead of a × c. That only works when a is 1." — rather than showing
   a red cross.
7. **Read the footer.** "Written with AI and checked by Kgomotso Sithole,
   BSc Mathematics." That is the line that makes a parent comfortable.

Then as `admin@dxstudenthelp.co.za`, open **AI Tutor** to see the topic, its
source material and both language versions with the reviewer named against each.

## 6. Notifications (2 minutes)

The bell in the header polls every 30 seconds. The notifications page lists
everything and lets the user choose in-app and email per event. Account,
security and guardian consent email can never be switched off.

## What is not built yet

Tutor self-onboarding with document upload, the admin console, resource library,
announcements, community, progress, monetisation, payments and the mobile apps.
Those are builds B4 to B11.
