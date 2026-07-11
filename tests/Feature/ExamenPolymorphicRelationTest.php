<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Examen;
use App\Models\Federation;
use Illuminate\Support\Str;

class ExamenPolymorphicRelationTest extends TestCase
{
    public function test_federation_is_resolved_for_examen_organisateur_relation(): void
    {
        $examen = new Examen([
            'organisateur_id' => Str::uuid(),
            'organisateur_type' => 'Federation',
        ]);

        $relatedModel = $examen->organisateur()->getRelated();

        $this->assertInstanceOf(Federation::class, $relatedModel);
    }
}
