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
        ];

        foreach ($systemPrompts as $data) {
            Prompt::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );
        }
    }
}
