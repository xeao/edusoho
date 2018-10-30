<?php

namespace ApiBundle\Api\Resource\Classroom;

use ApiBundle\Api\ApiRequest;
use ApiBundle\Api\Exception\ErrorCode;
use ApiBundle\Api\Resource\AbstractResource;
use Biz\Classroom\Service\ClassroomService;
use Biz\Exception\UnableJoinException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ClassroomMember extends AbstractResource
{
    public function add(ApiRequest $request, $classroomId)
    {
        $classroom = $this->getClassroomService()->getClassroom($classroomId);

        if (!$classroom) {
            throw new NotFoundHttpException('classroom.not_found', null, ErrorCode::RESOURCE_NOT_FOUND);
        }

        $member = $this->getClassroomService()->getClassroomMember($classroomId, $this->getCurrentUser()->getId());
        if (!$member || $member['role'] == array('auditor')) {
            $member = $this->tryJoin($classroom);
        }

        if ($member) {
            $this->getOCUtil()->single($member, array('userId'));
            $member['isOldUser'] = true;

            return $member;
        }

        return null;
    }

    private function tryJoin($classroom)
    {
        try {
            $this->getClassroomService()->tryFreeJoin($classroom['id']);
        } catch (UnableJoinException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e, $e->getCode());
        }

        $member = $this->getClassroomService()->getClassroomMember($classroom['id'], $this->getCurrentUser()->getId());
        if (!empty($member)) {
            $this->getLogService()->info('classroom', 'join_classroom', "加入 班级《{$classroom['title']}》", array('id' => $classroom['id'], 'title' => $classroom['title']));
        }

        return $member;
    }

    /**
     * @return \Biz\System\Service\Impl\LogServiceImpl
     */
    private function getLogService()
    {
        return $this->service('System:LogService');
    }

    /**
     * @return ClassroomService
     */
    private function getClassroomService()
    {
        return $this->service('Classroom:ClassroomService');
    }
}
