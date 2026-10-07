<?php

/**
 * Questions at all five levels. Every wrong option carries the misconception it
 * represents, which is what lets the result screen name the actual mistake
 * instead of showing a red cross.
 */
return [
    // Basic — can they recall the method?
    [
        'level' => 'basic',
        'question' => 'For 6x² + 11x + 3, what two numbers do you need to find?',
        'options' => [
            ['text' => 'Two numbers that multiply to 3 and add to 11', 'misconception' => 'Using c instead of a × c. That only works when a is 1.'],
            ['text' => 'Two numbers that multiply to 18 and add to 11', 'misconception' => ''],
            ['text' => 'Two numbers that multiply to 11 and add to 18', 'misconception' => 'Product and sum swapped. The product is a × c, the sum is b.'],
            ['text' => 'Two numbers that multiply to 6 and add to 3', 'misconception' => 'Using a and c as the targets rather than their product and b.'],
        ],
        'correct_index' => 1,
        'explanation' => 'Multiply a by c: 6 × 3 = 18. The sum you need is b, which is 11. The pair is 2 and 9.',
    ],
    [
        'level' => 'basic',
        'question' => 'What is the very first thing to check before factorising 12x² + 22x + 6?',
        'options' => [
            ['text' => 'Whether there is a common factor in all three terms', 'misconception' => ''],
            ['text' => 'Whether the answer will have brackets', 'misconception' => 'Every factorised trinomial has brackets, so this tells you nothing.'],
            ['text' => 'Whether x is positive', 'misconception' => 'x is a variable, not a known value, so this question does not apply.'],
            ['text' => 'Whether b is bigger than c', 'misconception' => 'The relative size of b and c does not affect the method.'],
        ],
        'correct_index' => 0,
        'explanation' => 'Every term is divisible by 2, so write 2(6x² + 11x + 3) and factorise what is inside.',
    ],
    [
        'level' => 'basic',
        'question' => 'In ax² + bx + c, which letter is the coefficient of the middle term?',
        'options' => [
            ['text' => 'a', 'misconception' => 'a is the coefficient of x², the first term.'],
            ['text' => 'b', 'misconception' => ''],
            ['text' => 'c', 'misconception' => 'c is the constant, the last term with no x.'],
            ['text' => 'x', 'misconception' => 'x is the variable, not a coefficient.'],
        ],
        'correct_index' => 1,
        'explanation' => 'b sits in front of x, the middle term.',
    ],
    [
        'level' => 'basic',
        'question' => 'How do you check a factorised answer?',
        'options' => [
            ['text' => 'Substitute x = 1', 'misconception' => 'This catches some errors but not all. Expanding is the reliable check.'],
            ['text' => 'Expand it and compare with the original', 'misconception' => ''],
            ['text' => 'Count the brackets', 'misconception' => 'The number of brackets says nothing about whether they are correct.'],
            ['text' => 'Add the coefficients', 'misconception' => 'There is no rule connecting the sum of coefficients to a correct factorisation.'],
        ],
        'correct_index' => 1,
        'explanation' => 'Expanding must return the original expression exactly.',
    ],

    // Easy — can they apply it to a straightforward case?
    [
        'level' => 'easy',
        'question' => 'Factorise 2x² + 7x + 6.',
        'options' => [
            ['text' => '(2x + 3)(x + 2)', 'misconception' => ''],
            ['text' => '(2x + 2)(x + 3)', 'misconception' => 'Expanding gives 2x² + 8x + 6. The middle term is wrong — check by expanding.'],
            ['text' => '(2x + 6)(x + 1)', 'misconception' => 'Expanding gives 2x² + 8x + 6. Also, 2x + 6 still has a common factor of 2.'],
            ['text' => '(x + 3)(x + 2)', 'misconception' => 'This expands to x² + 5x + 6. The 2 in front of x² has been lost.'],
        ],
        'correct_index' => 0,
        'explanation' => 'a × c = 12, b = 7, so the pair is 3 and 4. Split: 2x² + 3x + 4x + 6. Group: x(2x + 3) + 2(2x + 3) = (2x + 3)(x + 2).',
    ],
    [
        'level' => 'easy',
        'question' => 'Split the middle term of 3x² + 10x + 8.',
        'options' => [
            ['text' => '3x² + 4x + 6x + 8', 'misconception' => ''],
            ['text' => '3x² + 5x + 5x + 8', 'misconception' => '5 and 5 add to 10 but multiply to 25, not 24.'],
            ['text' => '3x² + 2x + 8x + 8', 'misconception' => '2 and 8 add to 10 but multiply to 16, not 24.'],
            ['text' => '3x² + 3x + 7x + 8', 'misconception' => '3 and 7 add to 10 but multiply to 21, not 24.'],
        ],
        'correct_index' => 0,
        'explanation' => 'a × c = 24 and b = 10, so the pair is 4 and 6: they multiply to 24 and add to 10.',
    ],
    [
        'level' => 'easy',
        'question' => 'After grouping, you have 3x(2x + 1) + 4(2x + 3). What has gone wrong?',
        'options' => [
            ['text' => 'Nothing, this is correct', 'misconception' => 'The brackets must match before you can factorise further.'],
            ['text' => 'The brackets do not match, so the split or a factor is wrong', 'misconception' => ''],
            ['text' => 'The 4 should be negative', 'misconception' => 'A sign change would not make the brackets match.'],
            ['text' => 'You should add the brackets together', 'misconception' => 'Brackets are never added in this method. They must match so one can be taken out.'],
        ],
        'correct_index' => 1,
        'explanation' => 'Matching brackets are the whole point of grouping. Go back and re-check the split and the factor taken out of each pair.',
    ],
    [
        'level' => 'easy',
        'question' => 'In 5x² − 11x + 2, what are the signs of the two numbers you need?',
        'options' => [
            ['text' => 'Both positive', 'misconception' => 'Two positives would give a positive middle term, but b is −11.'],
            ['text' => 'Both negative', 'misconception' => ''],
            ['text' => 'One positive, one negative', 'misconception' => 'That happens when c is negative. Here c is +2.'],
            ['text' => 'It does not matter', 'misconception' => 'Signs decide whether the method works at all.'],
        ],
        'correct_index' => 1,
        'explanation' => 'c is positive so the numbers share a sign, and b is negative so both are negative. The pair is −1 and −10.',
    ],

    // Intermediate — negatives and rearranging
    [
        'level' => 'intermediate',
        'question' => 'Factorise 3x² − 10x + 8.',
        'options' => [
            ['text' => '(3x − 4)(x − 2)', 'misconception' => ''],
            ['text' => '(3x + 4)(x + 2)', 'misconception' => 'Expanding gives 3x² + 10x + 8. The signs are wrong: b is negative.'],
            ['text' => '(3x − 2)(x − 4)', 'misconception' => 'Expanding gives 3x² − 14x + 8. The numbers are in the wrong brackets.'],
            ['text' => '(3x − 8)(x − 1)', 'misconception' => 'Expanding gives 3x² − 11x + 8. Check the middle term by expanding.'],
        ],
        'correct_index' => 0,
        'explanation' => 'a × c = 24, b = −10, so the pair is −4 and −6. Split: 3x² − 4x − 6x + 8. Group: x(3x − 4) − 2(3x − 4) = (3x − 4)(x − 2).',
    ],
    [
        'level' => 'intermediate',
        'question' => 'Factorise 4x² − 9x − 9.',
        'options' => [
            ['text' => '(4x + 3)(x − 3)', 'misconception' => ''],
            ['text' => '(4x − 3)(x + 3)', 'misconception' => 'Expanding gives 4x² + 9x − 9. The sign of the middle term is flipped — the signs are in the wrong brackets.'],
            ['text' => '(2x − 3)(2x + 3)', 'misconception' => 'This is a difference of squares, 4x² − 9, with no middle term at all.'],
            ['text' => '(4x − 9)(x + 1)', 'misconception' => 'Expanding gives 4x² − 5x − 9.'],
        ],
        'correct_index' => 0,
        'explanation' => 'a × c = −36, b = −9, so the pair is 3 and −12. Split: 4x² + 3x − 12x − 9. Group: x(4x + 3) − 3(4x + 3) = (4x + 3)(x − 3).',
    ],
    [
        'level' => 'intermediate',
        'question' => 'Factorise 6 + 5x − 6x² completely.',
        'options' => [
            ['text' => '(3x + 2)(2 − 3x)', 'misconception' => 'Expanding gives −9x² + ... — check by expanding.'],
            ['text' => '−(3x + 2)(2x − 3)', 'misconception' => ''],
            ['text' => '(6 − x)(1 + 6x)', 'misconception' => 'Expanding gives 6 + 35x − 6x². The middle term is wrong.'],
            ['text' => 'It does not factorise', 'misconception' => 'It does. Rearrange into standard form first: −6x² + 5x + 6.'],
        ],
        'correct_index' => 1,
        'explanation' => 'Rearrange to −6x² + 5x + 6, then take out −1: −(6x² − 5x − 6) = −(3x + 2)(2x − 3).',
    ],
    [
        'level' => 'intermediate',
        'question' => 'Factorise 12x² + 22x + 6 completely.',
        'options' => [
            ['text' => '(6x + 2)(2x + 3)', 'misconception' => 'This expands correctly but is not complete: 6x + 2 still has a common factor of 2.'],
            ['text' => '2(3x + 1)(2x + 3)', 'misconception' => ''],
            ['text' => '2(6x² + 11x + 3)', 'misconception' => 'The common factor is out, but the trinomial inside has not been factorised.'],
            ['text' => '(12x + 6)(x + 1)', 'misconception' => 'Expanding gives 12x² + 18x + 6.'],
        ],
        'correct_index' => 1,
        'explanation' => 'Take out 2 first: 2(6x² + 11x + 3), then factorise inside to get 2(3x + 1)(2x + 3). "Completely" means no factor is left behind.',
    ],

    // Difficult — fractions, substitution, reasoning backwards
    [
        'level' => 'difficult',
        'question' => 'Factorise 8x² − 2x − 15.',
        'options' => [
            ['text' => '(4x + 5)(2x − 3)', 'misconception' => ''],
            ['text' => '(4x − 5)(2x + 3)', 'misconception' => 'Expanding gives 8x² + 2x − 15. The middle sign is flipped.'],
            ['text' => '(8x + 5)(x − 3)', 'misconception' => 'Expanding gives 8x² − 19x − 15.'],
            ['text' => '(2x − 5)(4x + 3)', 'misconception' => 'Expanding gives 8x² − 14x − 15.'],
        ],
        'correct_index' => 0,
        'explanation' => 'a × c = −120, b = −2, so the pair is 10 and −12. Split: 8x² + 10x − 12x − 15. Group: 2x(4x + 5) − 3(4x + 5) = (4x + 5)(2x − 3).',
    ],
    [
        'level' => 'difficult',
        'question' => 'One factor of 6x² + kx − 10 is (3x − 2). What is k?',
        'options' => [
            ['text' => '11', 'misconception' => 'Check by expanding: (3x − 2)(2x + 5) gives +11x, but then the constant would be −10 with a different pairing. Expand carefully.'],
            ['text' => '−11', 'misconception' => 'This would need the other factor to be (2x − 5), giving a constant of +10, not −10.'],
            ['text' => '1', 'misconception' => 'This would need the other factor to be (2x + ...), but no integer pairing gives +1 here.'],
            ['text' => '−1', 'misconception' => 'Expanding with this k does not reproduce the constant −10.'],
        ],
        'correct_index' => 0,
        'explanation' => 'If (3x − 2) is a factor, the other must be (2x + 5) to give 6x² and −10. Expanding: 15x − 4x = 11x, so k = 11.',
    ],
    [
        'level' => 'difficult',
        'question' => 'Factorise 15x² − 34xy + 15y² .',
        'options' => [
            ['text' => '(5x − 3y)(3x − 5y)', 'misconception' => ''],
            ['text' => '(5x − 5y)(3x − 3y)', 'misconception' => 'Expanding gives 15x² − 30xy + 15y². Also both brackets still have common factors.'],
            ['text' => '(15x − y)(x − 15y)', 'misconception' => 'Expanding gives 15x² − 226xy + 15y².'],
            ['text' => '(5x + 3y)(3x + 5y)', 'misconception' => 'Both signs positive gives a positive middle term, but b is negative.'],
        ],
        'correct_index' => 0,
        'explanation' => 'Treat y as part of the constant: a × c = 225, b = −34, so the pair is −9 and −25. The same method works with two variables.',
    ],
    [
        'level' => 'difficult',
        'question' => 'For which value of c does 4x² + 12x + c factorise into two identical brackets?',
        'options' => [
            ['text' => '9', 'misconception' => ''],
            ['text' => '12', 'misconception' => 'a × c would be 48, and no pair multiplying to 48 adds to 12 with equal brackets.'],
            ['text' => '16', 'misconception' => 'a × c would be 64, and the pair 8 and 8 gives (2x + ...) brackets that are not identical here.'],
            ['text' => '36', 'misconception' => 'Too large: the middle term would need to be much bigger than 12.'],
        ],
        'correct_index' => 0,
        'explanation' => 'A perfect square needs (2x + 3)² = 4x² + 12x + 9. So c = 9.',
    ],

    // Extremely difficult — exam-level reasoning
    [
        'level' => 'extreme',
        'question' => 'Factorise 6x⁴ + 11x² + 3 completely.',
        'options' => [
            ['text' => '(3x² + 1)(2x² + 3)', 'misconception' => ''],
            ['text' => '(3x + 1)(2x + 3)', 'misconception' => 'The powers have been lost. Expanding gives 6x² + 11x + 3, not a quartic.'],
            ['text' => '(6x² + 1)(x² + 3)', 'misconception' => 'Expanding gives 6x⁴ + 19x² + 3.'],
            ['text' => 'It does not factorise', 'misconception' => 'It does. Let y = x² and it becomes a familiar trinomial.'],
        ],
        'correct_index' => 0,
        'explanation' => 'Substitute y = x², giving 6y² + 11y + 3 = (3y + 1)(2y + 3). Replace y: (3x² + 1)(2x² + 3). Neither bracket factorises further over the reals.',
    ],
    [
        'level' => 'extreme',
        'question' => 'Simplify (6x² + 11x + 3) ÷ (2x² + 5x + 3).',
        'options' => [
            ['text' => '(3x + 1)/(x + 1)', 'misconception' => ''],
            ['text' => '3', 'misconception' => 'You cannot divide term by term. Factorise both and cancel the bracket they share.'],
            ['text' => '(2x + 3)/(x + 1)', 'misconception' => 'The wrong bracket has been cancelled. (2x + 3) is the shared one, so it disappears.'],
            ['text' => 'It does not simplify', 'misconception' => 'Both factorise and share the bracket (2x + 3).'],
        ],
        'correct_index' => 0,
        'explanation' => 'Top: (3x + 1)(2x + 3). Bottom: (2x + 3)(x + 1). Cancel (2x + 3), leaving (3x + 1)/(x + 1).',
    ],
    [
        'level' => 'extreme',
        'question' => 'For 2x² + bx + 6 to factorise over the integers, which set of b values is complete?',
        'options' => [
            ['text' => '±7, ±8, ±13', 'misconception' => ''],
            ['text' => '±7 only', 'misconception' => 'The pairs (2,6), (1,12) and (3,4) all multiply to 12, so more than one b works.'],
            ['text' => 'Any even number', 'misconception' => 'b must come from a factor pair of a × c = 12, not from any even value.'],
            ['text' => '±5, ±7, ±11', 'misconception' => 'These do not all arise from factor pairs of 12.'],
        ],
        'correct_index' => 0,
        'explanation' => 'a × c = 12. Its factor pairs are (1,12), (2,6) and (3,4), summing to 13, 8 and 7. Negative pairs give the negatives.',
    ],
    [
        'level' => 'extreme',
        'question' => 'A rectangle has area 6x² + 11x + 3 and one side 3x + 1. Its perimeter is:',
        'options' => [
            ['text' => '10x + 8', 'misconception' => ''],
            ['text' => '5x + 4', 'misconception' => 'That is the sum of one length and one width. Perimeter is twice that.'],
            ['text' => '6x² + 11x + 3', 'misconception' => 'That is the area, not the perimeter.'],
            ['text' => '(3x + 1)(2x + 3)', 'misconception' => 'That is the factorised area, which is the two sides multiplied, not added.'],
        ],
        'correct_index' => 0,
        'explanation' => 'The other side is (2x + 3). Perimeter = 2(3x + 1) + 2(2x + 3) = 6x + 2 + 4x + 6 = 10x + 8.',
    ],
];
