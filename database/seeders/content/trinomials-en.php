<?php

/**
 * Grade 11 Mathematics: factorising trinomials where a ≠ 1.
 * Written by hand to show what a finished AI Tutor lesson should look like,
 * so DX can judge the format on real content rather than filler.
 */
return [
    'title' => 'Factorising trinomials when a is not 1',
    'summary' => 'How to factorise expressions like 6x² + 11x + 3, using the method markers expect to see.',
    'objectives' => [
        'Recognise when a trinomial has a leading coefficient other than 1',
        'Use the product-sum method to split the middle term',
        'Factorise by grouping and check by expanding',
    ],
    'minutes' => 22,
    'segments' => [
        [
            'title' => 'What makes this harder',
            'narration' => "You already know how to factorise x² + 5x + 6. You look for two numbers that multiply to 6 and add to 5, and you write (x + 2)(x + 3).\n\nNow look at 6x² + 11x + 3. The 6 in front of x² changes things. Two numbers that multiply to 3 and add to 11 do not exist. So the old method stops working, and we need a slightly longer one.\n\nThe good news is that the longer method works every time, including on the easy ones.",
            'visual' => 'Two expressions side by side: x² + 5x + 6 with a tick, 6x² + 11x + 3 with a question mark over the 6.',
            'check' => [
                'question' => 'Why can you not just find two numbers that multiply to 3 and add to 11?',
                'answer' => 'Because the 6 in front of x² spreads across both brackets, so the numbers you need are not simply the factors of the last term.',
            ],
        ],
        [
            'title' => 'The product and the sum',
            'narration' => "Write the expression in the standard form ax² + bx + c. For 6x² + 11x + 3, that gives a = 6, b = 11 and c = 3.\n\nNow multiply a by c. Six times three is eighteen. Hold that number: eighteen is the product you need.\n\nThe sum you need is b, which is eleven.\n\nSo the question becomes: which two numbers multiply to 18 and add to 11?",
            'visual' => 'An arc drawn from the 6 to the 3 labelled "multiply = 18", and an arrow to the 11 labelled "add".',
            'check' => [
                'question' => 'For 2x² + 7x + 6, what is the product you need?',
                'answer' => 'Two times six is twelve. You need two numbers that multiply to 12 and add to 7.',
            ],
        ],
        [
            'title' => 'Finding the pair',
            'narration' => "List the factor pairs of eighteen: one and eighteen, two and nine, three and six.\n\nNow check which pair adds to eleven. One plus eighteen is nineteen, too big. Two plus nine is eleven. That is the pair.\n\nThree plus six is nine, which is too small, so you can stop at two and nine.\n\nIf no pair works, the trinomial does not factorise over the integers. That happens, and the correct answer is to say so.",
            'visual' => 'The factor pairs of 18 listed, with 2 and 9 highlighted and 11 written beside it.',
            'check' => [
                'question' => 'Which two numbers multiply to 12 and add to 7?',
                'answer' => 'Three and four.',
            ],
        ],
        [
            'title' => 'Splitting the middle term',
            'narration' => "Here is the step that makes everything else work. Replace the middle term with your two numbers.\n\n6x² + 11x + 3 becomes 6x² + 2x + 9x + 3.\n\nNotice that nothing has changed in value: 2x plus 9x is still 11x. You have just written it in a more useful shape.\n\nThe order of the two middle terms does not matter. Try it both ways and you get the same final answer.",
            'visual' => 'The 11x term expanding into 2x + 9x, with the other terms staying still.',
            'check' => [
                'question' => 'Split the middle term of 2x² + 7x + 6.',
                'answer' => '2x² + 3x + 4x + 6.',
            ],
        ],
        [
            'title' => 'Grouping: fully worked',
            'narration' => "Take 6x² + 2x + 9x + 3 and group it into two pairs: the first two terms, then the last two.\n\nFrom 6x² + 2x, the common factor is 2x. Taking it out gives 2x(3x + 1).\n\nFrom 9x + 3, the common factor is 3. Taking it out gives 3(3x + 1).\n\nNow you have 2x(3x + 1) + 3(3x + 1). The bracket (3x + 1) appears in both, so take it out as a common factor: (3x + 1)(2x + 3).\n\nThat is the answer.",
            'visual' => 'The four terms bracketed into two pairs, each pair reducing to a common factor, with (3x + 1) circled in both.',
            'check' => [
                'question' => 'Why must the two brackets match before you can continue?',
                'answer' => 'Because the matching bracket is the common factor. If they do not match, you have taken out the wrong factor or split the middle term wrongly.',
            ],
        ],
        [
            'title' => 'Your turn, with one step shown',
            'narration' => "Factorise 2x² + 7x + 6.\n\nYou already found the split: 2x² + 3x + 4x + 6.\n\nGrouping the first pair gives x(2x + 3).\n\nNow do the second pair yourself. What is the common factor of 4x and 6, and what does the bracket become?\n\nWhen you have it, take out the matching bracket.",
            'visual' => 'The first grouping completed, the second grouping left blank for the learner.',
            'check' => [
                'question' => 'What is the fully factorised answer?',
                'answer' => '2(2x + 3) comes from the second pair, giving (2x + 3)(x + 2).',
            ],
        ],
        [
            'title' => 'Your turn, unaided',
            'narration' => "Factorise 3x² − 10x + 8 completely.\n\nWork through it the same way: find a times c, find the pair that multiplies to it and adds to b, split, group, and take out the common bracket.\n\nWatch the signs. When c is positive and b is negative, both your numbers are negative.\n\nTake your time, then check your answer by expanding.",
            'visual' => 'A blank worked-solution frame with the five step labels and space for the learner.',
            'check' => [
                'question' => 'What is the answer?',
                'answer' => '(3x − 4)(x − 2). The pair is −4 and −6, since they multiply to 24 and add to −10.',
            ],
        ],
        [
            'title' => 'Always check, and what markers want',
            'narration' => "Expand your answer to check. (3x − 4)(x − 2) gives 3x² − 6x − 4x + 8, which is 3x² − 10x + 8. Correct.\n\nTwo things that cost marks in exams. First, always take out a common factor before you start: 12x² + 22x + 6 has a 2 in every term, so factorise that out first.\n\nSecond, show your working. A correct final answer with no method earns fewer marks than correct working with a small slip at the end. Write the product and sum, write the split, write the grouping.",
            'visual' => 'Expansion shown line by line returning to the original, and a marking grid showing where method marks are awarded.',
            'check' => [
                'question' => 'What should you always do before applying the method?',
                'answer' => 'Check for a common factor in all three terms and take it out first.',
            ],
        ],
    ],
    'notes' => "# Factorising trinomials when a ≠ 1\n\n## The method\n\nFor **ax² + bx + c**:\n\n1. **Take out any common factor first.**\n2. Work out **a × c**.\n3. Find two numbers that **multiply to a × c** and **add to b**.\n4. **Split the middle term** using those two numbers.\n5. **Group** into two pairs and take out the common factor of each.\n6. The brackets must match — take the matching bracket out.\n7. **Check by expanding.**\n\n## Worked example\n\n6x² + 11x + 3\n\n- a × c = 6 × 3 = 18, b = 11\n- 2 × 9 = 18 and 2 + 9 = 11\n- 6x² + 2x + 9x + 3\n- 2x(3x + 1) + 3(3x + 1)\n- **(3x + 1)(2x + 3)**\n\n## Signs\n\n| c | b | Your two numbers |\n|---|---|---|\n| positive | positive | both positive |\n| positive | negative | both negative |\n| negative | either | one positive, one negative |\n\n## Common mistakes\n\n- Forgetting the common factor at the start\n- Using factors of c instead of a × c\n- Brackets that do not match after grouping — go back and re-split\n- No working shown, so no method marks",
    'flashcards' => [
        ['front' => 'For ax² + bx + c, what do your two numbers multiply to?', 'back' => 'a × c, not just c.'],
        ['front' => 'What do your two numbers add to?', 'back' => 'b, the coefficient of the middle term.'],
        ['front' => 'First step, always?', 'back' => 'Take out a common factor from all three terms.'],
        ['front' => 'c is positive, b is negative. What are the signs of your numbers?', 'back' => 'Both negative.'],
        ['front' => 'Your two brackets do not match after grouping. What now?', 'back' => 'You split the middle term wrongly, or took out the wrong factor. Go back to the split.'],
        ['front' => 'How do you check a factorised answer?', 'back' => 'Expand it and confirm you get the original expression.'],
    ],
];
