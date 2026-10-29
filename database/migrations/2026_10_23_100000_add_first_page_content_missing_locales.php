<?php

use App\Http\Middleware\SetLocaleFromHeader;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owner 2026-10-23: the Greek home page showed most blocks in English. da, el, nl, pt and sq have no
 * translation rows for the home page sections and items (only the hero title, added by
 * 2026_10_23_060000), so the API falls back to English.
 *
 * Idempotent: a missing row is inserted (metadata copied from English); a row that still holds the English
 * text is updated; a row someone translated stays as it is. Sections are matched by code, items by their
 * English title.
 */
return new class extends Migration
{
    public const LOCALES = ['da', 'el', 'nl', 'pt', 'sq'];

    /** Section code => locale => [title, description|null]. */
    public const SECTIONS = [
        'browse_map' => [
            'da' => ['Gennemse kortet med over 1500 strande', 'Opdag verdens smukkeste strande med et interaktivt kort med over 1500 fantastiske steder. Uanset om du leder efter paradisiske sandstrande, skjulte bugter eller populære surfdestinationer, finder du den perfekte strand for dig. Se beskrivelser, bedømmelser og billeder, og planlæg dit næste drømmeeventyr ved havet!'],
            'el' => ['Εξερευνήστε τον χάρτη με πάνω από 1500 παραλίες', 'Ανακαλύψτε τις ομορφότερες παραλίες του κόσμου με έναν διαδραστικό χάρτη με πάνω από 1500 εκπληκτικές τοποθεσίες. Είτε ψάχνετε παραδεισένιες αμμουδιές, κρυφούς κολπίσκους ή δημοφιλείς προορισμούς για σέρφινγκ, θα βρείτε την ιδανική παραλία για εσάς. Δείτε περιγραφές, βαθμολογίες και φωτογραφίες και σχεδιάστε την επόμενη ονειρεμένη απόδρασή σας στη θάλασσα!'],
            'nl' => ['Verken de kaart met meer dan 1500 stranden', 'Ontdek de mooiste stranden ter wereld met een interactieve kaart met meer dan 1500 prachtige locaties. Of je nu op zoek bent naar paradijselijke zandstranden, verborgen baaien of populaire surfspots, je vindt hier het perfecte strand voor jou. Bekijk beschrijvingen, beoordelingen en foto\'s en plan je volgende droomavontuur aan zee!'],
            'pt' => ['Explore o mapa com mais de 1500 praias', 'Descubra as praias mais bonitas do mundo com um mapa interativo com mais de 1500 locais deslumbrantes. Quer procure praias de areia paradisíacas, enseadas escondidas ou destinos de surf populares, encontrará a praia perfeita para si. Consulte descrições, avaliações e fotografias e planeie a sua próxima aventura de sonho à beira-mar!'],
            'sq' => ['Shfletoni hartën me mbi 1500 plazhe', 'Zbuloni plazhet më të bukura në botë me një hartë interaktive me mbi 1500 vende mahnitëse. Qoftë nëse kërkoni bregdete parajsore me rërë, gjire të fshehura apo destinacione të njohura për surf, do të gjeni plazhin e përsosur për ju. Shikoni përshkrimet, vlerësimet dhe fotot dhe planifikoni aventurën tuaj të radhës buzë detit!'],
        ],
        'find_beach' => [
            'da' => ['Find en strand efter din smag', 'Leder du efter den perfekte strand? Brug de avancerede filtre og find hurtigt det ideelle sted! Søg efter strandtype, sandtype, tilgængelighed, popularitet, aktiviteter, faciliteter og meget mere. Uanset om du ønsker et stille hjørne til afslapning eller en livlig strand med masser af vandsport, finder du nemt det, der passer dig bedst.'],
            'el' => ['Βρείτε την παραλία που σας ταιριάζει', 'Ψάχνετε την τέλεια παραλία; Χρησιμοποιήστε τα σύνθετα φίλτρα και βρείτε γρήγορα την ιδανική τοποθεσία! Αναζητήστε ανά τύπο παραλίας, τύπο άμμου, προσβασιμότητα, δημοτικότητα, δραστηριότητες, υποδομές και πολλά άλλα. Είτε θέλετε μια ήσυχη γωνιά για χαλάρωση είτε μια ζωντανή παραλία με πολλά θαλάσσια σπορ, θα βρείτε εύκολα αυτό που σας ταιριάζει καλύτερα.'],
            'nl' => ['Vind een strand naar jouw smaak', 'Op zoek naar het perfecte strand? Gebruik de geavanceerde filters en vind snel de ideale plek! Zoek op type strand, soort zand, toegankelijkheid, populariteit, activiteiten, voorzieningen en meer. Of je nu een rustig plekje wilt om te ontspannen of een levendig strand met volop watersport, je vindt gemakkelijk wat het beste bij je past.'],
            'pt' => ['Encontre uma praia ao seu gosto', 'Procura a praia perfeita? Use os filtros avançados e encontre rapidamente o local ideal! Pesquise por tipo de praia, tipo de areia, acessibilidade, popularidade, atividades, infraestruturas e muito mais. Quer pretenda um recanto tranquilo para relaxar ou uma praia animada com muitos desportos aquáticos, encontrará facilmente o que mais lhe convém.'],
            'sq' => ['Gjeni një plazh sipas shijes suaj', 'Po kërkoni plazhin e përsosur? Përdorni filtrat e avancuar dhe gjeni shpejt vendin ideal! Kërkoni sipas llojit të plazhit, llojit të rërës, aksesueshmërisë, popullaritetit, aktiviteteve, infrastrukturës dhe më shumë. Qoftë nëse dëshironi një qoshe të qetë për pushim apo një plazh të gjallë me shumë sporte ujore, do të gjeni lehtësisht atë që ju përshtatet më mirë.'],
        ],
        'beach_views' => [
            'da' => ['Se strandene fra alle vinkler', null],
            'el' => ['Δείτε τις παραλίες από κάθε οπτική γωνία', null],
            'nl' => ['Bekijk stranden vanuit alle hoeken', null],
            'pt' => ['Veja as praias de todos os ângulos', null],
            'sq' => ['Shikoni plazhet nga të gjitha këndet', null],
        ],
        'accommodation' => [
            'da' => ['Slap af i behagelig overnatning ved stranden', 'Oplev fuldkommen afslapning i en bolig lige ved havet! Vælg mellem luksusresorts, charmerende bungalows eller lejligheder med udsigt over bølgerne. Uanset din rejsestil venter komfort, nærhed til stranden og en uforglemmelig oplevelse ved havet.'],
            'el' => ['Χαλαρώστε σε άνετα καταλύματα δίπλα στην παραλία', 'Ζήστε απόλυτη χαλάρωση σε καταλύματα δίπλα στη θάλασσα! Επιλέξτε ανάμεσα σε πολυτελή θέρετρα, γοητευτικά μπανγκαλόου ή διαμερίσματα με θέα στα κύματα. Όποιο κι αν είναι το ταξιδιωτικό σας στυλ, σας περιμένουν άνεση, εγγύτητα στην παραλία και μια αξέχαστη εμπειρία δίπλα στη θάλασσα.'],
            'nl' => ['Ontspan in comfortabele accommodatie aan het strand', 'Beleef volledige ontspanning in een accommodatie direct aan zee! Kies uit luxe resorts, charmante bungalows of appartementen met uitzicht op de golven. Wat je reisstijl ook is, comfort, de nabijheid van het strand en een onvergetelijke ervaring aan zee wachten op je.'],
            'pt' => ['Relaxe em alojamento confortável junto à praia', 'Viva o relaxamento total num alojamento mesmo à beira-mar! Escolha entre resorts de luxo, bungalows encantadores ou apartamentos com vista para as ondas. Seja qual for o seu estilo de viagem, esperam-no conforto, proximidade da praia e uma experiência inesquecível junto ao mar.'],
            'sq' => ['Pushoni në akomodim të rehatshëm pranë plazhit', 'Përjetoni çlodhje të plotë në akomodim pranë detit! Zgjidhni mes resorteve luksoze, bungalove simpatike ose apartamenteve me pamje nga valët. Pavarësisht stilit tuaj të udhëtimit, ju presin rehatia, afërsia me plazhin dhe një përvojë e paharrueshme buzë detit.'],
        ],
        'coastal_destinations' => [
            'da' => ['Verdens smukkeste kystdestinationer', null],
            'el' => ['Οι ομορφότεροι παραθαλάσσιοι προορισμοί του κόσμου', null],
            'nl' => ['De mooiste kustbestemmingen ter wereld', null],
            'pt' => ['Os destinos costeiros mais bonitos do mundo', null],
            'sq' => ['Destinacionet bregdetare më të bukura në botë', null],
        ],
        'footer_text' => [
            'da' => ['Udforsk, drøm, opdag paradisstrande!', null],
            'el' => ['Εξερευνήστε, ονειρευτείτε, ανακαλύψτε παραδεισένιες παραλίες!', null],
            'nl' => ['Verken, droom, ontdek paradijselijke stranden!', null],
            'pt' => ['Explore, sonhe, descubra praias paradisíacas!', null],
            'sq' => ['Eksploroni, ëndërroni, zbuloni plazhe parajsore!', null],
        ],
    ];

    /** Item English title => locale => title. */
    public const ITEMS = [
        'Gallery' => ['da' => 'Galleri', 'el' => 'Συλλογή', 'nl' => 'Galerij', 'pt' => 'Galeria', 'sq' => 'Galeria'],
        'Video' => ['da' => 'Video', 'el' => 'Βίντεο', 'nl' => 'Video', 'pt' => 'Vídeo', 'sq' => 'Video'],
        'Spheres' => ['da' => 'Sfærer', 'el' => 'Σφαίρες', 'nl' => 'Sferen', 'pt' => 'Esferas', 'sq' => 'Sfera'],
        'Live' => ['da' => 'Live', 'el' => 'Ζωντανά', 'nl' => 'Live', 'pt' => 'Ao vivo', 'sq' => 'Live'],
        '100 most beautiful beaches in the world' => [
            'da' => 'Verdens 100 smukkeste strande', 'el' => 'Οι 100 ομορφότερες παραλίες του κόσμου',
            'nl' => 'De 100 mooiste stranden ter wereld', 'pt' => 'As 100 praias mais bonitas do mundo',
            'sq' => '100 plazhet më të bukura në botë',
        ],
        'Discover beach stories and tips' => [
            'da' => 'Opdag strandhistorier og tips', 'el' => 'Ανακαλύψτε ιστορίες και συμβουλές για παραλίες',
            'nl' => 'Ontdek strandverhalen en tips', 'pt' => 'Descubra histórias e dicas de praia',
            'sq' => 'Zbuloni histori dhe këshilla për plazhet',
        ],
        'Relax in comfortable accommodation' => [
            'da' => 'Slap af i behagelig overnatning', 'el' => 'Χαλαρώστε σε άνετα καταλύματα',
            'nl' => 'Ontspan in comfortabele accommodatie', 'pt' => 'Relaxe em alojamento confortável',
            'sq' => 'Pushoni në akomodim të rehatshëm',
        ],
        'Discover a beach to your taste' => [
            'da' => 'Find en strand efter din smag', 'el' => 'Ανακαλύψτε την παραλία που σας ταιριάζει',
            'nl' => 'Ontdek een strand naar jouw smaak', 'pt' => 'Descubra uma praia ao seu gosto',
            'sq' => 'Zbuloni një plazh sipas shijes suaj',
        ],
    ];

    public function up(): void
    {
        foreach (self::SECTIONS as $code => $byLocale) {
            $id = DB::table('first_page_content')->where('code', $code)->value('id');
            if (! $id) {
                continue;
            }
            foreach ($byLocale as $locale => [$title, $description]) {
                $this->fill('first_page_content_translation', 'first_page_content_id', $id, $locale, $title, $description);
            }
        }

        foreach (self::ITEMS as $english => $byLocale) {
            $itemIds = DB::table('first_page_content_item_translation')
                ->where('locale', 'en')->where('title', $english)->pluck('first_page_content_item_id');
            foreach ($itemIds as $itemId) {
                foreach ($byLocale as $locale => $title) {
                    $this->fill('first_page_content_item_translation', 'first_page_content_item_id', $itemId, $locale, $title, null);
                }
            }
        }

        foreach (SetLocaleFromHeader::SUPPORTED_LOCALES as $locale) {
            Cache::forget("first_page_content:index:{$locale}");
        }
    }

    /** Insert a missing row, or replace a row that still carries the English text; leave translated rows alone. */
    private function fill(string $table, string $fk, int $parentId, string $locale, string $title, ?string $description): void
    {
        $en = DB::table($table)->where($fk, $parentId)->where('locale', 'en')->first();
        $row = DB::table($table)->where($fk, $parentId)->where('locale', $locale)->first();
        // A null translation keeps whatever English description there is (sections without one have none).
        $description ??= $en?->description;

        if (! $row) {
            DB::table($table)->insert([
                $fk => $parentId,
                'locale' => $locale,
                'title' => $title,
                'description' => $description,
                'metadata' => $en?->metadata,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        $update = [];
        if ($en && $row->title === $en->title && $en->title !== $title) {
            $update['title'] = $title;
        }
        if ($en && $en->description !== null && $row->description === $en->description && $description !== $en->description) {
            $update['description'] = $description;
        }
        if ($update) {
            DB::table($table)->where('id', $row->id)->update($update + ['updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Content fix: translations stay.
    }
};
