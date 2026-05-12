<?php

namespace Tests\Feature\Recipes;

use App\Jobs\SyncRecipeToSearch;
use App\Models\DietTag;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\LoginTestUser;
use Tests\TestCase;

class RecipeDietTagControllerTest extends TestCase
{
    use RefreshDatabase, LoginTestUser;

    protected Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->login();
        Queue::fake();
        $this->recipe = Recipe::factory()->create();
    }

    // -------------------------------------------------------------------------
    // attach
    // -------------------------------------------------------------------------

    public function test_attach_adds_diet_tag_to_recipe(): void
    {
        $tag = DietTag::factory()->create();

        $response = $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.diet-tags.attach', $this->recipe), ['diet_tag_id' => $tag->id])
            ->assertStatus(200);

        $this->assertDatabaseHas('recipe_diet_tag', [
            'recipe_id'   => $this->recipe->id,
            'diet_tag_id' => $tag->id,
        ]);
        $this->assertCount(1, $response->json());
    }

    public function test_attach_is_idempotent(): void
    {
        $tag = DietTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.diet-tags.attach', $this->recipe), ['diet_tag_id' => $tag->id])
            ->assertStatus(200);

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.diet-tags.attach', $this->recipe), ['diet_tag_id' => $tag->id])
            ->assertStatus(200);

        $this->assertDatabaseCount('recipe_diet_tag', 1);
    }

    public function test_attach_requires_diet_tag_id(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.diet-tags.attach', $this->recipe), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['diet_tag_id']);
    }

    public function test_attach_rejects_nonexistent_diet_tag(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.diet-tags.attach', $this->recipe), ['diet_tag_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['diet_tag_id']);
    }

    public function test_attach_dispatches_sync_job(): void
    {
        $tag = DietTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->postJson(route('recipes.diet-tags.attach', $this->recipe), ['diet_tag_id' => $tag->id]);

        Queue::assertPushed(SyncRecipeToSearch::class, fn($job) =>
            $job->id === $this->recipe->id
        );
    }

    // -------------------------------------------------------------------------
    // detach
    // -------------------------------------------------------------------------

    public function test_detach_removes_single_tag(): void
    {
        $tags = DietTag::factory()->count(2)->create();
        $this->recipe->dietTags()->attach($tags->pluck('id'));

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.diet-tags.detach', [$this->recipe, $tags->first()]))
            ->assertStatus(204);

        $this->assertDatabaseMissing('recipe_diet_tag', [
            'recipe_id'   => $this->recipe->id,
            'diet_tag_id' => $tags->first()->id,
        ]);
        $this->assertDatabaseHas('recipe_diet_tag', [
            'recipe_id'   => $this->recipe->id,
            'diet_tag_id' => $tags->last()->id,
        ]);
    }

    public function test_detach_nonattached_tag_is_idempotent(): void
    {
        $tag = DietTag::factory()->create();

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.diet-tags.detach', [$this->recipe, $tag]))
            ->assertStatus(204);
    }

    // -------------------------------------------------------------------------
    // detachAll
    // -------------------------------------------------------------------------

    public function test_detach_all_removes_all_tags(): void
    {
        $tags = DietTag::factory()->count(3)->create();
        $this->recipe->dietTags()->attach($tags->pluck('id'));

        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.diet-tags.detach-all', $this->recipe))
            ->assertStatus(204);

        $this->assertDatabaseCount('recipe_diet_tag', 0);
    }

    public function test_detach_all_when_no_tags_is_idempotent(): void
    {
        $this->withHeaders($this->makeAuthRequestHeader())
            ->deleteJson(route('recipes.diet-tags.detach-all', $this->recipe))
            ->assertStatus(204);
    }

    protected function tearDown(): void
    {
        $this->logout();
        parent::tearDown();
    }
}
