<?php

namespace App\Services;

use App\Enums\TypeUserEnum;
use App\Exceptions\MembersExists;
use App\Mail\RegisterEmail;
use App\Repositories\Interfaces\GroupRepositoryInterface;
use App\Repositories\Interfaces\MemberRepositoryInterface;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\MemberHasGroupRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MemberService
{
    public function __construct(
        private GroupRepositoryInterface $groupRepository,
        private MemberRepositoryInterface $memberRepository,
        private UserRepositoryInterface $userRepository,
        private MemberHasGroupRepository $memberHasGroupRepository
    ) {
    }

    public function list(string $groupId)
    {
        $group = $this->groupRepository->findById($groupId);
        return $group->userMembers;
    }

    /**
     * @throws Throwable
     */
    public function createMany(string $groupId, array $data): void
    {
        try {
            DB::beginTransaction();
            $this->groupRepository->findById($groupId);

            foreach ($data as $payload) {
                $payload = array_merge($payload, ['group_id' => $groupId]);
                $this->createGroupHasMembers($payload);
            }

            DB::commit();
        } catch (Throwable $throwable) {
            DB::rollBack();
            throw $throwable;
        }
    }

    public function edit(string $id, array $data): Model
    {
        $data = Arr::only($data, ['role', 'phone', 'entry_date', 'departure_date']);
        return $this->memberRepository->update($id, $data);
    }

    /**
     * @throws Throwable
     */
    public function delete(string $groupId, string $memberId): void
    {
        $this->groupRepository->findById($groupId);
        $this->memberRepository->delete($memberId);
    }

    private function createGroupHasMembers(array $data): void
    {
        $groupId = Arr::get($data, 'group_id');
        $email = Arr::get($data, 'email');
        $user = $this->userRepository->findByFilters(['email' => $email]);

        if ($user->isNotEmpty()) {
            $userId = $user->first()->id;
            $memberData = array_merge($data, ['user_id' => $userId]);
        } else {
            Mail::to($email)->send(new RegisterEmail(TypeUserEnum::MEMBER));
            $memberData = $data;
        }

        $member = $this->memberRepository->create($memberData);

        $this->memberHasGroupRepository->create([
            'member_id' => $member->id,
            'group_id'  => $groupId
        ]);
    }
}
