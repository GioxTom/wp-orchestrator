<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Seeder;

class PromptSeeder extends Seeder
{
    public function run(): void
    {
        $systemPrompts = [
            [
                'name'    => 'Logo Generation — Default',
                'slug'    => 'logo-generation-default',
                'action'  => 'logo_generation',
                'type'    => 'system',
                'content' => <<<'PROMPT'
Create a professional, modern logo for a website with the following details:
- Website name: {site_name}
- Description: {site_description}
- Language/Market: {locale}

Design requirements:
- Clean, minimal and memorable
- Suitable for a professional web presence
- Works well at small sizes (favicon) and large sizes (header)
- White background, transparent-friendly design
- Include the website name as text in the logo
- Use a cohesive color palette of 2-3 colors maximum

Return only the logo image, square format (1:1), no additional text or explanation.
PROMPT,
            ],

            [
                'name'    => 'Category Generation — Default',
                'slug'    => 'category-generation-default',
                'action'  => 'category_generation',
                'type'    => 'system',
                'content' => <<<'PROMPT'
Sei un esperto di content strategy e SEO per siti web.

Genera esattamente {count} categorie WordPress per il seguente sito web:

Nome sito: {site_name}
Descrizione: {description}
Lingua: {lang}

Requisiti:
- Le categorie devono essere pertinenti al tema del sito
- Devono essere scritte nella lingua indicata ({lang})
- Devono essere concise (1-3 parole ciascuna)
- Devono essere uniche e non sovrapporsi tra loro
- Devono coprire i principali argomenti del sito
- Non includere "Generale" o "Varie" come categorie

Rispondi ESCLUSIVAMENTE con un array JSON di stringhe, senza nessun testo aggiuntivo, senza backtick, senza spiegazioni.
Esempio di risposta corretta: ["Tecnologia", "Sport", "Cultura", "Economia"]
PROMPT,
            ],
        ];

        foreach ($systemPrompts as $data) {
            Prompt::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
