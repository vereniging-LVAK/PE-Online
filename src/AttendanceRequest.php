<?php

namespace PeOnline;

/**
 * Attendance request object for attendance submission
 */
class AttendanceRequest
{
    private int $orgId;
    private ?int $peCourseId = null;
    private ?string $externalCourseId = null;
    private ?int $pePersonId = null;
    private ?string $externalPersonId = null;
    private string $endDate;
    private ?int $peModuleId = null;
    private ?string $externalModuleId = null;

    public function __construct(
        int $orgId,
        string $endDate,
        ?int $peCourseId = null,
        ?string $externalCourseId = null,
        ?int $pePersonId = null,
        ?string $externalPersonId = null,
        ?int $peModuleId = null,
        ?string $externalModuleId = null
    ) {
        $this->orgId = $orgId;
        $this->endDate = $endDate;
        $this->peCourseId = $peCourseId;
        $this->externalCourseId = $externalCourseId;
        $this->pePersonId = $pePersonId;
        $this->externalPersonId = $externalPersonId;
        $this->peModuleId = $peModuleId;
        $this->externalModuleId = $externalModuleId;
    }

    public function getOrgId(): int
    {
        return $this->orgId;
    }

    public function getPeCourseId(): ?int
    {
        return $this->peCourseId;
    }

    public function getExternalCourseId(): ?string
    {
        return $this->externalCourseId;
    }

    public function getPerPersonId(): ?int
    {
        return $this->pePersonId;
    }

    public function getExternalPersonId(): ?string
    {
        return $this->externalPersonId;
    }

    public function getEndDate(): string
    {
        return $this->endDate;
    }

    public function getPeModuleId(): ?int
    {
        return $this->peModuleId;
    }

    public function getExternalModuleId(): ?string
    {
        return $this->externalModuleId;
    }

    /**
     * Convert request to array for JSON serialization
     *
     * @return array
     */
    public function toArray(): array
    {
        $data = [
            'orgId' => $this->orgId,
            'endDate' => $this->endDate,
        ];

        if ($this->peCourseId !== null) {
            $data['peCourseId'] = $this->peCourseId;
        }

        if ($this->externalCourseId !== null) {
            $data['externalCourseId'] = $this->externalCourseId;
        }

        if ($this->pePersonId !== null) {
            $data['pePersonId'] = $this->pePersonId;
        }

        if ($this->externalPersonId !== null) {
            $data['externalPersonId'] = $this->externalPersonId;
        }

        if ($this->peModuleId !== null) {
            $data['peModuleId'] = $this->peModuleId;
        }

        if ($this->externalModuleId !== null) {
            $data['externalModuleId'] = $this->externalModuleId;
        }

        return $data;
    }
}