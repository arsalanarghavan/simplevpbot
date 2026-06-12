<?php

namespace Tests\Feature;

use App\Support\MutateOpCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateSmokeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function allOpsProvider(): array
    {
        $out = [];
        foreach (MutateOpCatalog::all() as $op) {
            $out[$op] = [$op];
        }

        return $out;
    }

    /** @dataProvider allOpsProvider */
    public function test_mutate_op_returns_structured_response(string $op): void
    {
        $this->actingAsAdmin();

        $payload = array_merge(['op' => $op], $this->mutatePayloadFor($op));
        $response = $this->postJson('/api/v1/admin/mutate', $payload);

        $response->assertJsonStructure(['ok']);
        $data = $response->json();
        $this->assertIsBool($data['ok']);
        if (! $data['ok']) {
            $this->assertNotEmpty($data['message'] ?? $data['reason'] ?? null, "Op {$op} failed without message");
        }
    }
}
