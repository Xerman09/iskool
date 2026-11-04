<?php
namespace Modules\Chat\Repositories;

use App\Models\User;
use Modules\Chat\Entities\BlockUser;
use Modules\Chat\Entities\Conversation;
use Modules\Chat\Entities\Group;
use Modules\Chat\Entities\Invitation;
use Modules\Chat\Notifications\InvitationNotification;


class GroupRepository
{

    protected $group;

    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getAllGroup()
    {
        /* This term's classes only.

           A class group belongs to one subject in one term, and a student takes a
           new set every term - but membership is never removed, so the list used to
           carry every class anyone had ever been enrolled in. By the second year it
           is four timetables deep and the current one is somewhere inside it.

           Nothing is deleted: last term's group still exists and its conversation
           with it, and it returns to view whenever that term is the one running.
           A group made by hand through Chat has no subject and no term, so it is
           never filtered - it is nobody's timetable and belongs to whoever is in it. */
        $groups = Group::whereHas('users', function($q){
            $q->where('user_id', auth()->id());
        })->where(function ($q) {
            $q->whereNull('subject_id')
                ->orWhere(function ($classGroup) {
                    $classGroup->where('academic_id', getAcademicId())
                        ->where(function ($term) {
                            /* A group raised before the term was recorded keeps a null
                               one; showing it is the safer mistake. */
                            $term->whereNull('semester_id')
                                ->orWhere('semester_id', activeSemesterId());
                        });
                });
        })->get();

        foreach ($groups as $group){
            $last = $group->threads()->first();
            $group->custom_order = $last->id ?? 0;
        }

        return $groups->sortByDesc(function ($group){
            return $group->custom_order;
        })->values();
    }

}
