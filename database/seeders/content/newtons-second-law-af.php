<?php

/**
 * Newton's second law in Afrikaans. Technical terms and symbols stay in
 * English, matching the bilingual rule used for the isiZulu lesson.
 */
return [
    'segments' => [
        [
            'title' => 'Netto krag, nie net krag nie',
            'narration' => "Die wet sê F_net = ma. Die woord wat die meeste punte kos, is \"netto\".\n\n'n Krat van 10 kg wat met 50 N gestoot word terwyl wrywing met 20 N terugdruk, versnel nie teen 5 m·s⁻² nie. Die netto krag is 50 − 20 = 30 N, dus is a = 30 ÷ 10 = 3 m·s⁻².\n\nVoordat jy aan die formule raak, tel elke krag op wat op die voorwerp inwerk, met rigting. Daardie som is wat in F_net ingaan.",
            'visual' => "'n Krat met 'n 50 N pyl regs en 'n 20 N pyl links, wat na 'n enkele 30 N pyl regs vereenvoudig.",
            'check' => ['question' => "'n Boks van 4 kg het 12 N vorentoe en 4 N wrywing. Wat is a?", 'answer' => 'F_net = 8 N, dus a = 2 m·s⁻².'],
        ],
        [
            'title' => 'Die vryeliggaamdiagram',
            'narration' => "Teken die voorwerp as 'n punt. Teken elke krag as 'n pyl wat by daardie punt wegbeweeg. Benoem elkeen.\n\nVir 'n boks op 'n tafel: gewig (Fg of mg) af, normaalkrag (F_N) op, toegepaste krag langs die oppervlak, wrywing teen die beweging in.\n\nTwee reëls wat mense uitvang. Net kragte wat OP die voorwerp inwerk kom op die diagram — nie kragte wat die voorwerp op iets anders uitoefen nie. En die normaalkrag is nie altyd gelyk aan mg nie; op 'n helling is dit kleiner.",
            'visual' => "'n Punt met vier benoemde pyle, langs 'n deurgehaalde diagram waar 'n reaksiekrag verkeerdelik ingesluit is.",
            'check' => ['question' => 'Hoekom is F_N nie gelyk aan mg op \'n helling nie?', 'answer' => 'Omdat slegs die komponent van gewig loodreg op die oppervlak daarteen druk: F_N = mg·cos θ.'],
        ],
        [
            'title' => "Kies 'n positiewe rigting",
            'narration' => "Kies 'n rigting en noem dit positief. Skryf dit neer. Elke krag in daardie rigting is positief, elke krag daarteen is negatief.\n\nDit maak nie saak watter rigting jy kies nie, solank jy konsekwent bly. Om die rigting van beweging te kies hou die getalle gewoonlik positief.\n\nAs jou antwoord negatief uitkom, is dit nie 'n fout nie. Dit beteken die versnelling is teenoorgesteld aan die rigting wat jy gekies het.",
            'visual' => 'Dieselfde probleem twee keer opgelos met teenoorgestelde positiewe rigtings, met dieselfde fisiese antwoord.',
            'check' => ['question' => 'Jou berekening gee a = −2 m·s⁻². Wat beteken dit?', 'answer' => 'Die versnelling is 2 m·s⁻² in die rigting teenoorgesteld aan dié wat jy as positief gekies het.'],
        ],
        [
            'title' => 'Uitgewerkte voorbeeld: die helling',
            'narration' => "'n Blok van 5 kg gly teen 'n wrywinglose helling van 30° af. Bereken sy versnelling.\n\nNeem af-met-die-helling as positief. Die enigste krag langs die helling is die komponent van gewig: mg·sin θ.\n\nDit is 5 × 9.8 × sin 30° = 5 × 9.8 × 0.5 = 24.5 N.\n\nF_net = ma, dus 24.5 = 5a, wat a = 4.9 m·s⁻² af met die helling gee.\n\nLet op dat die massa uitkanselleer as jy simbolies werk: a = g·sin θ. Elke voorwerp gly teen dieselfde tempo teen 'n wrywinglose helling af.",
            'visual' => "'n Helling met gewig ontbind in komponente parallel en loodreg op die oppervlak.",
            'check' => ['question' => "Wat is die versnelling op 'n wrywinglose helling van 20°?", 'answer' => 'a = 9.8 × sin 20° = 3.4 m·s⁻².'],
        ],
        [
            'title' => 'Jou beurt: voeg wrywing by',
            'narration' => "Dieselfde blok van 5 kg, dieselfde helling van 30°, maar nou met 'n wrywingskrag van 10 N op met die helling.\n\nJy weet reeds die komponent van gewig af met die helling is 24.5 N.\n\nBereken F_net, dan a. Neem af-met-die-helling as positief.\n\nAs jy dit het, vra jouself of die antwoord groter of kleiner as 4.9 behoort te wees, en kontroleer dat dit so is.",
            'visual' => "Dieselfde helling met 'n wrywingspyl bygevoeg, en 'n leë oplossingsraam.",
            'check' => ['question' => 'Wat is die versnelling?', 'answer' => 'F_net = 24.5 − 10 = 14.5 N, dus a = 2.9 m·s⁻² af met die helling. Kleiner as voorheen, soos verwag.'],
        ],
        [
            'title' => 'Verbinde voorwerpe, en wat nasieners wil sien',
            'narration' => "Twee bokse wat met 'n tou verbind is, beweeg saam, dus deel hulle een versnelling. Jy kan hulle as een voorwerp met 'n gekombineerde massa hanteer om a te vind, en dan na een boks alleen kyk om die spanning te vind.\n\n'n Boks van 3 kg en een van 2 kg wat met 20 N getrek word: a = 20 ÷ 5 = 4 m·s⁻². Dan vir die 2 kg boks alleen, T = ma = 8 N.\n\nIn die eksamen: teken altyd die vryeliggaamdiagram, meld altyd jou positiewe rigting, skryf altyd F_net = ma neer voor jy substitueer. Dit is metodepunte wat jy kry selfs as die rekenkunde glip.",
            'visual' => "Twee bokse met 'n tou, eers as 'n stelsel opgelos en dan as een boks vir spanning.",
            'check' => ['question' => 'Hoekom kan jy verbinde voorwerpe eers as een massa hanteer?', 'answer' => 'Omdat die tou hulle dwing om dieselfde versnelling te hê, en die spanning intern tot die stelsel is.'],
        ],
    ],
    'notes' => "# Newton se tweede wet\n\n**F_net = ma**\n\n## Metode\n\n1. Teken 'n **vryeliggaamdiagram** — elke krag wat OP die voorwerp inwerk.\n2. Kies en **meld 'n positiewe rigting**.\n3. Skryf **F_net = ma** neer voor jy substitueer.\n4. Tel kragte met tekens op, los op vir die onbekende.\n5. Kontroleer die teken en die grootte van jou antwoord.\n\n## Kragte\n\n| Krag | Simbool | Notas |\n|---|---|---|\n| Gewig | Fg = mg | altyd reguit af, g = 9.8 m·s⁻² |\n| Normaal | F_N | loodreg op die oppervlak; = mg·cos θ op 'n helling |\n| Wrywing | f = μF_N | teen die beweging in |\n| Spanning | T | dieselfde deur 'n ideale tou |\n\n## Hellings\n\n- Langs die helling: mg·sin θ\n- In die helling in: mg·cos θ\n- Wrywingloos: a = g·sin θ, onafhanklik van massa\n\n## Algemene foute\n\n- Toegepaste krag gebruik in plaas van netto krag\n- Reaksiekragte op die vryeliggaamdiagram\n- Aanvaar F_N = mg op 'n helling\n- Positiewe rigting nie gemeld nie, dan tekens verloor",
];
