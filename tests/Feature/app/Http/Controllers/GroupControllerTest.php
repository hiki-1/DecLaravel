<?php

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\TypeGroupEnum;
use App\Enums\TypeUserEnum;
use App\Models\Group;
use App\Models\Representative;
use App\Models\TypeGroup;
use App\Models\TypeUser;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Utils\LoginUsersTrait;
use Tests\TestCase;

class GroupControllerTest extends TestCase
{
    use DatabaseTransactions;
    use LoginUsersTrait;

    const BASE_URL = 'api/group';

    public function setUp(): void
    {
        $this->faker = FakerFactory::create();
        parent::setUp(); // TODO: Change the autogenerated stub
    }

    public function testShouldListAll()
    {
        $this->login(TypeUserEnum::MANAGER);
        Group::factory(10)->create();

        $response = $this->get(self::BASE_URL, ['status' => 'EM ANDAMENTO']);

        $response->assertStatus(200);
        $this->assertCount(10, Group::all());
    }

    public function testShouldListByFilters()
    {
        $this->login(TypeUserEnum::MANAGER);
        $user = User::factory()->create();
        Group::factory(['creator_user_id' => $user])->create();

        $response = $this->get(self::BASE_URL, ['creator_user_id' => $user->id, ['status' => 'EM ANDAMENTO']]);

        $response->assertStatus(200);
        $this->assertCount(1, json_decode($response->getContent(), true)['data']);
    }

    public function testShouldListOne()
    {
        $this->login(TypeUserEnum::MANAGER);
        $group = Group::factory()->create();

        $response = $this->get(sprintf('%s/%s', self::BASE_URL, $group->id));

        $actual = json_decode($response->getContent(), true)['data'];
        $response->assertStatus(200);
        $this->assertCount(1, $group->all());
    }

    public function testShouldNotFoundGroup()
    {
        $this->login(TypeUserEnum::MANAGER);

        $response = $this->get(sprintf('%s/100', self::BASE_URL));

        $actual = json_decode($response->getContent(), true);
        $response->assertStatus(404);
        $this->assertEquals('Grupo não encontrado', $actual['errors']);
    }

    public function testShouldCreate()
    {
        Mail::fake();

        $this->login(TypeUserEnum::MANAGER);

        $payload = $this->fakePayload();
        $response = $this->post(self::BASE_URL, $payload);

        $actual = json_decode($response->getContent(), true)['data'];
        $response->assertStatus(201);
        $this->assertDatabaseHas('groups', Arr::except($actual, ['created_by', 'type_group', 'representative', 'members']));
        $this->assertDatabaseHas('representatives', Arr::get($actual, 'representative'));
        $this->assertDatabaseHas('type_groups', Arr::only($payload, ['name', 'type_group']));
    }

    public function testShouldOnlyManagersCreate()
    {
        Mail::fake();

        $this->login(TypeUserEnum::REPRESENTATIVE);

        $payload = $this->fakePayload();
        $response = $this->post(self::BASE_URL, $payload);

        $actual = json_decode($response->getContent(), true);
        $response->assertStatus(403);
        $this->assertEquals('This action is unauthorized.', $actual['errors']);
    }

    public function testShouldCreateWithRegisteredRepresentativesIntoSystem()
    {
        Mail::fake();
        $this->login(TypeUserEnum::MANAGER);

        $user = User::factory(['type_user_id' => 3])->create();
        $payload = $this->fakePayload();
        $payload['representative'] = $user->email;

        $response = $this->post(self::BASE_URL, $payload);

        $representative = Representative::where(['email' => $user->email])->first();
        $response->assertStatus(201);
        $this->assertEquals($representative->user_id, $user->id);
    }

    public function testShouldCreateNotRegisteredRepresentativesIntoSystem()
    {
        $this->login(TypeUserEnum::MANAGER);

        $payload = $this->fakePayload();
        $payload['representative'] = 'outroteste@Mail.com';

        Mail::fake();

        $response = $this->post(self::BASE_URL, $payload);

        $representative = Representative::where(['email' => 'outroteste@Mail.com'])->first();
        $response->assertStatus(201);
        $this->assertEquals(null, $representative->user_id);
    }

    public function testShouldUpdate()
    {
        Mail::fake();

        $this->login(TypeUserEnum::MANAGER);

        $group = Group::factory()->create();
        $payload = $this->fakePayload();
        $payload['entity'] = 'teste';
        $response = $this->put(sprintf('%s/%s', self::BASE_URL, $group->id), $payload);

        $actual = json_decode($response->getContent(), true)['data'];
        $response->assertStatus(200);
        $this->assertEquals($payload['entity'], $actual['entity']);
        $this->assertDatabaseHas('representatives', $actual['representative']);
    }

    public function testShouldOnlyManagersUpdate()
    {
        $this->login(TypeUserEnum::REPRESENTATIVE);

        $group = Group::factory()->create();
        $payload = $this->fakePayload();
        $payload['entity'] = 'teste';
        $response = $this->put(sprintf('%s/%s', self::BASE_URL, $group->id), $payload);
        $actual = json_decode($response->getContent(), true);

        $response->assertStatus(403);
        $this->assertEquals('This action is unauthorized.', $actual['errors']);
    }

    public function testShouldOnlyManagerCreatedCanUpdate()
    {
        $this->login(TypeUserEnum::MANAGER);

        $typeUserId = TypeUser::where(['name' => TypeUserEnum::MANAGER])->first()->id;
        $user = User::factory(['type_user_id' => $typeUserId])->create();
        $group = Group::factory(['creator_user_id' => $user->id])->create();
        $payload = $this->fakePayload();
        $payload['entity'] = 'teste';
        $response = $this->put(sprintf('%s/%s', self::BASE_URL, $group->id), $payload);

        $actual = json_decode($response->getContent(), true);
        $response->assertStatus(403);
        $this->assertEquals('This action is unauthorized.', $actual['errors']);
    }

    public function testShouldUpdateRepresentativeRegisteredInSystem()
    {
        Mail::fake();

        $this->login(TypeUserEnum::MANAGER);

        $group = Group::factory()->create();
        $payload = $this->fakePayload();
        $user = User::factory(['type_user_id' => 3])->create();
        $payload['representative'] = $user->email;
        $response = $this->put(sprintf('%s/%s', self::BASE_URL, $group->id), $payload);
        $actual = json_decode($response->getContent(), true);
        $representative = Representative::where(['email' => $user->email])->first();
        $response->assertStatus(200);
        $this->assertEquals($representative->user_id, $user->id);
        $this->assertEquals($representative->id, $actual['data']['representative']['id']);
    }

    public function testShouldUpdateRepresentativeNotRegisteredInSystem()
    {
        Mail::fake();
        $this->login(TypeUserEnum::MANAGER);

        $group = Group::factory()->create();
        $payload = $this->fakePayload();
        $payload['representative'] = 'teste@teste.com';
        $response = $this->put(sprintf('%s/%s', self::BASE_URL, $group->id), $payload);
        $actual = json_decode($response->getContent(), true);
        $representative = Representative::where(['email' => 'teste@teste.com'])->first();
        $response->assertStatus(200);
        $this->assertEquals(null, $representative->user_id);
        $this->assertEquals($representative->id, $actual['data']['representative']['id']);
    }

    public function testShouldDelete()
    {
        $this->login(TypeUserEnum::MANAGER);

        $typeGroup = TypeGroup::factory()->create();
        $representative = Representative::factory()->create();
        $group = Group::factory(['type_group_id' => $typeGroup->id, 'representative_id' => $representative->id])->create();
        $response = $this->delete(sprintf('%s/%s', self::BASE_URL, $group->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('groups', $group->toArray());
        $this->assertDatabaseMissing('representatives', $representative->toArray());
        $this->assertDatabaseMissing('type_groups', $typeGroup->toArray());
    }

    public function testShouldOnlyManagerCreatedCanDelete()
    {
        $this->login(TypeUserEnum::MANAGER);

        $typeUserId = TypeUser::where(['name' => TypeUserEnum::MANAGER])->first()->id;
        $user = User::factory(['type_user_id' => $typeUserId])->create();
        $group = Group::factory(['creator_user_id' => $user->id])->create();

        $response = $this->delete(sprintf('%s/%s', self::BASE_URL, $group->id));
        $actual = json_decode($response->getContent(), true);

        $response->assertStatus(403);
        $this->assertEquals('This action is unauthorized.', $actual['errors']);
    }

    private function fakePayload(): array
    {
        $typeGroup = TypeGroup::factory()->create();
        User::factory()->create(['type_user_id' => 3]);

        $user = User::where(['type_user_id' => 3])->first();
        return [
            'entity'             => $this->faker->word,
            'organ'              => $this->faker->word,
            'council'            => $this->faker->word,
            'acronym'            => 'SIGLA',
            'team'               => $this->faker->word,
            'unit'               => $this->faker->word,
            'email'              => $this->faker->email,
            'office_requested'   => $this->faker->word,
            'office_indicated'   => $this->faker->word,
            'internal_concierge' => $this->faker->word,
            'observations'       => $this->faker->text,
            'status'             => $this->faker->randomElement(['EM ANDAMENTO', 'FINALIZADO']),
            'type_group_id'      => $typeGroup->id,
            'representative'     => $user->email,
            'name'               => 'Comissão',
            'type_group'         => TypeGroupEnum::INTERNO,
        ];
    }
}
