<?php

/**
 * The same lesson in isiZulu, showing the bilingual rule in practice: the
 * explanation is in isiZulu, but technical terms, formulas and labels stay in
 * English, because the NSC paper is written in English.
 */
return [
    'segments' => [
        [
            'title' => 'Yini eyenza lokhu kube nzima',
            'narration' => "Usuyazi ukufactorise (ukuhlukanisa) u-x² + 5x + 6. Ufuna izinombolo ezimbili ezikhuphulana zikhiphe u-6 futhi zihlanganiswe zikhiphe u-5, bese ubhala (x + 2)(x + 3).\n\nManje bheka u-6x² + 11x + 3. U-6 ophambi kuka-x² ushintsha izinto. Azikho izinombolo ezimbili ezikhuphulana zikhiphe u-3 zibuye zihlanganiswe zikhiphe u-11. Ngakho indlela endala iyama lapha.\n\nIzindaba ezinhle ukuthi indlela entsha isebenza njalo, ngisho nakuleyo elula.",
            'visual' => 'Izinkulumo ezimbili eduze: x² + 5x + 6 enophawu lokuvuma, no-6x² + 11x + 3 onophawu lombuzo ku-6.',
            'check' => [
                'question' => 'Kungani ungeke umane uthole izinombolo ezikhuphulana zikhiphe u-3 zihlanganiswe zikhiphe u-11?',
                'answer' => 'Ngoba u-6 ophambi kuka-x² usabalala kuwo womabili ama-brackets, ngakho izinombolo ozidingayo akuzona nje ama-factors we-last term.',
            ],
        ],
        [
            'title' => 'I-product ne-sum',
            'narration' => "Bhala inkulumo ngendlela ejwayelekile: ax² + bx + c. Ku-6x² + 11x + 3, lokho kunika a = 6, b = 11 no-c = 3.\n\nManje khuphula u-a ngo-c. Isithupha siphindwe kathathu kunika ishumi nesishiyagalombili. Bamba leyo nombolo: u-18 uyi-product oyidingayo.\n\nI-sum oyidingayo ngu-b, okungu-11.\n\nUmbuzo-ke uba: yiziphi izinombolo ezimbili ezikhuphulana zikhiphe u-18 zihlanganiswe zikhiphe u-11?",
            'visual' => 'Umugqa osuka ku-6 uya ku-3 obhalwe "multiply = 18", nomcibisholo oya ku-11 obhalwe "add".',
            'check' => [
                'question' => 'Ku-2x² + 7x + 6, yini i-product oyidingayo?',
                'answer' => 'Kubili kuphindwe ngesithupha kunika u-12. Udinga izinombolo ezimbili ezikhiphe u-12 ngokukhuphula, no-7 ngokuhlanganisa.',
            ],
        ],
        [
            'title' => 'Ukuthola izinombolo ezimbili',
            'narration' => "Bhala phansi ama-factor pairs ka-18: u-1 no-18, u-2 no-9, u-3 no-6.\n\nManje bheka ukuthi yiliphi iphea elikhipha u-11 uma uhlanganisa. U-1 no-18 unika u-19, mkhulu kakhulu. U-2 no-9 unika u-11. Yilelo iphea.\n\nUma lingekho iphea elisebenzayo, le trinomial ayifactorise ngezinombolo eziphelele. Kuyenzeka lokho, futhi impendulo elungile ukusho kanjalo.",
            'visual' => 'Ama-factor pairs ka-18 abhaliwe, u-2 no-9 egqamisiwe no-11 eceleni.',
            'check' => [
                'question' => 'Yiziphi izinombolo ezikhipha u-12 ngokukhuphula no-7 ngokuhlanganisa?',
                'answer' => 'U-3 no-4.',
            ],
        ],
        [
            'title' => 'Ukuhlukanisa i-middle term',
            'narration' => "Nansi isinyathelo esenza konke kusebenze. Beka izinombolo zakho ezimbili esikhundleni se-middle term.\n\nU-6x² + 11x + 3 uba u-6x² + 2x + 9x + 3.\n\nQaphela ukuthi akukho okushintshile ngenani: u-2x no-9x usengu-11x. Umane uyibhale ngendlela esiza kakhudlwana.\n\nUkuhleleka kwala ma-terms amabili aphakathi akubalulekile. Zama zombili izindlela uthole impendulo efanayo.",
            'visual' => 'I-11x ihlukana ibe ngu-2x + 9x, amanye ama-terms ehlala njengoba enjalo.',
            'check' => [
                'question' => 'Hlukanisa i-middle term ka-2x² + 7x + 6.',
                'answer' => '2x² + 3x + 4x + 6.',
            ],
        ],
        [
            'title' => 'I-grouping: isibonelo esiphelele',
            'narration' => "Thatha u-6x² + 2x + 9x + 3 bese uwahlukanisa abe ngamaphea amabili: ama-terms amabili okuqala, bese kuba ngamabili okugcina.\n\nKu-6x² + 2x, i-common factor ngu-2x. Uma uyikhipha uthola u-2x(3x + 1).\n\nKu-9x + 3, i-common factor ngu-3. Uma uyikhipha uthola u-3(3x + 1).\n\nManje unawo u-2x(3x + 1) + 3(3x + 1). I-bracket (3x + 1) ivela kuwo womabili, ngakho yikhiphe njenge-common factor: (3x + 1)(2x + 3).\n\nLeyo yimpendulo.",
            'visual' => 'Ama-terms amane ahlukaniswe abe ngamaphea amabili, iphea ngalinye likhiphe i-common factor, u-(3x + 1) uzungezwe kuwo womabili.',
            'check' => [
                'question' => 'Kungani ama-brackets amabili kumele afane ngaphambi kokuqhubeka?',
                'answer' => 'Ngoba i-bracket efanayo yiyona i-common factor. Uma ingafani, ukhiphe i-factor engalungile noma wahlukanise i-middle term ngendlela engalungile.',
            ],
        ],
        [
            'title' => 'Yenza wena, isinyathelo esisodwa sikhonjisiwe',
            'narration' => "Factorise u-2x² + 7x + 6.\n\nUsuvele uthole ukuhlukaniswa: 2x² + 3x + 4x + 6.\n\nUkuhlanganisa iphea lokuqala kunika u-x(2x + 3).\n\nManje yenza iphea lesibili wena. Ithini i-common factor ka-4x no-6, futhi iba yini i-bracket?\n\nUma usunayo, khipha i-bracket efanayo.",
            'visual' => 'I-grouping yokuqala igcwalisiwe, eyesibili ishiywe ingenalutho ukuze umfundi ayigcwalise.',
            'check' => [
                'question' => 'Ithini impendulo egcwele?',
                'answer' => 'U-2(2x + 3) uvela ephea lesibili, unika u-(2x + 3)(x + 2).',
            ],
        ],
        [
            'title' => 'Yenza wena, ngaphandle kosizo',
            'narration' => "Factorise u-3x² − 10x + 8 ngokuphelele.\n\nSebenza ngendlela efanayo: thola u-a uphindwe ngo-c, thola iphea elikhiphayo lelo nani ngokukhuphula futhi likhiphe u-b ngokuhlanganisa, hlukanisa, group, bese ukhipha i-bracket efanayo.\n\nQaphela ama-signs. Uma u-c emuhle (positive) kodwa u-b engemuhle (negative), zombili izinombolo zakho zingama-negative.\n\nThatha isikhathi sakho, bese uhlola impendulo ngokuyi-expand.",
            'visual' => 'Ifreyimu engenalutho enezinyathelo ezinhlanu nendawo yokubhala.',
            'check' => [
                'question' => 'Ithini impendulo?',
                'answer' => '(3x − 4)(x − 2). Iphea ngu-−4 no-−6, ngoba bakhipha u-24 ngokukhuphula no-−10 ngokuhlanganisa.',
            ],
        ],
        [
            'title' => 'Hlola njalo, nalokho ama-markers akufunayo',
            'narration' => "Yenza i-expand yempendulo yakho ukuze uhlole. U-(3x − 4)(x − 2) unika u-3x² − 6x − 4x + 8, okungu-3x² − 10x + 8. Kulungile.\n\nIzinto ezimbili ezidla amamaki ezivivinyweni. Okokuqala, khipha njalo i-common factor ngaphambi kokuqala: u-12x² + 22x + 6 unamabili kuwo wonke ama-terms, ngakho yikhiphe kuqala.\n\nOkwesibili, bonisa umsebenzi wakho. Impendulo elungile engenamsebenzi obonisiwe ithola amamaki ambalwa kunomsebenzi obonisiwe onephutha elincane ekugcineni. Bhala i-product ne-sum, bhala ukuhlukaniswa, bhala i-grouping.",
            'visual' => 'I-expansion ibonisiwe umugqa nomugqa ibuyela esiqalweni, negridi lamamaki libonisa lapho amamaki e-method anikezwa khona.',
            'check' => [
                'question' => 'Yini okumele uyenze njalo ngaphambi kokusebenzisa indlela?',
                'answer' => 'Hlola ukuthi ikhona yini i-common factor kuwo wonke ama-terms amathathu, bese uyikhipha kuqala.',
            ],
        ],
    ],
    'notes' => "# Ukufactorise ama-trinomials uma u-a engeyena u-1\n\n## Indlela\n\nKu-**ax² + bx + c**:\n\n1. **Khipha noma iyiphi i-common factor kuqala.**\n2. Bala u-**a × c**.\n3. Thola izinombolo ezimbili ezikhipha u-**a × c** ngokukhuphula, no-**b** ngokuhlanganisa.\n4. **Hlukanisa i-middle term** usebenzisa lezo zinombolo.\n5. **Group** ube ngamaphea amabili, ukhiphe i-common factor kwelinye nelinye.\n6. Ama-brackets kumele afane — khipha i-bracket efanayo.\n7. **Hlola nge-expanding.**\n\n## Isibonelo esisebenzayo\n\n6x² + 11x + 3\n\n- a × c = 6 × 3 = 18, b = 11\n- 2 × 9 = 18 futhi 2 + 9 = 11\n- 6x² + 2x + 9x + 3\n- 2x(3x + 1) + 3(3x + 1)\n- **(3x + 1)(2x + 3)**\n\n## Amaphutha avamile\n\n- Ukukhohlwa i-common factor ekuqaleni\n- Ukusebenzisa ama-factors ka-c esikhundleni sika-a × c\n- Ama-brackets angafani ngemva kwe-grouping — buyela ekuhlukaniseni\n- Ukungabonisi umsebenzi, ngakho awutholi amamaki e-method",
];
