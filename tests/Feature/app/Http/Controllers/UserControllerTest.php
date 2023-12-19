<?php

namespace Tests\Feature\app\Http\Controllers;

use App\Enums\TypeUserEnum;
use App\Models\TypeUser;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Utils\LoginUsersTrait;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use DatabaseTransactions;
    use LoginUsersTrait;

    public function testIndexUsers()
    {
        $this->login(TypeUserEnum::ADMIN);
        // Cria 10 usuários no banco de dados usando o model factory
        User::factory(5)->create();

        // Envia uma solicitação para listar todos os usuários
        $response = $this->get('/api/users');

        // Verifica se a solicitação foi bem-sucedida e se a resposta contém os usuários
        $response->assertStatus(200);
        $this->assertCount(15, User::all());
    }

    public function testShouldListByFilters()
    {
        $this->login(TypeUserEnum::ADMIN);

        $data = ['email' => 'teste@gmail.com'];
        User::factory($data)->create();

        $response = $this->get('/api/users?email=teste@gmail.com');

        $response->assertStatus(200);
        $this->assertCount(1, json_decode($response->getContent(), true)['data']);
    }

    public function testShouldNotListUsersWithoutPermission()
    {
        $this->login(TypeUserEnum::VIEWER);
        User::factory(10)->create();

        $response = $this->get('/api/users');
        $response->assertStatus(403);
    }

    /**
     * Teste de falha: Verificar se um usuário inexistente retorna um erro 404.
     *
     * @return void
     */
    public function testIndexNotExistsUser()
    {
        $this->login(TypeUserEnum::ADMIN);

        // Cria um ID inválido para um usuário inexistente
        $invalidId = 999;

        // Envia uma solicitação para exibir o usuário inexistente
        $response = $this->getJson('/api/users/' . $invalidId);

        // Verifica se a solicitação retornou um erro 404
        $response->assertStatus(404);
    }

    public function testShowUser()
    {
        $this->login(TypeUserEnum::ADMIN);

        // Cria um usuário no banco de dados usando o model factory
        $user = User::factory()->create();

        // Envia uma solicitação para exibir o usuário criado
        $response = $this->getJson('/api/users/' . $user->id);

        // Verifica se a solicitação foi bem-sucedida e se os dados retornados são corretos
        $response->assertStatus(200)
            ->assertJsonCount(1);
    }

    /**
     * Teste de falha: Verificar se um usuário inexistente retorna um erro 404.
     *
     * @return void
     */
    public function testShowNotExistUser()
    {
        $this->login(TypeUserEnum::ADMIN);

        // Cria um ID inválido para um usuário inexistente
        $invalidId = 999;

        // Envia uma solicitação para exibir o usuário inexistente
        $response = $this->getJson('/api/users/' . $invalidId);

        // Verifica se a solicitação retornou um erro 404
        $response->assertStatus(404);
    }

    public function testShouldUpdate()
    {
        $user = $this->login(TypeUserEnum::ADMIN);

        $response = $this->put(sprintf('api/users/%s', $user->id), ['name' => 'outro nome']);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testShouldNotUpdateWithoutPermission()
    {
        $this->login(TypeUserEnum::ADMIN);

        $response = $this->put(sprintf('api/users/%s', 1), ['name' => 'outro nome']);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShouldNotUpdateOthersUsers()
    {
        $this->login(TypeUserEnum::ADMIN);
        $user = User::factory()->create();

        $response = $this->put(sprintf('api/users/%s', $user->id), ['name' => 'outro nome']);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShouldDestrou()
    {
        $this->login(TypeUserEnum::ADMIN);
        $user = User::factory()->create();

        $response = $this->delete(sprintf('api/users/%s', $user->id), ['name' => 'outro nome']);

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testShouldNotDestroyWithoutPermissions()
    {
        $this->login(TypeUserEnum::VIEWER);
        $user = User::factory()->create();

        $response = $this->delete(sprintf('api/users/%s', $user->id), ['name' => 'outro nome']);

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testShouldUpdateEmail()
    {
        $user = $this->login(TypeUserEnum::ADMIN);

        $response = $this->put(sprintf('api/users/%s', $user->id), ['email' => $user->email, 'name' => 'outronome']);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
