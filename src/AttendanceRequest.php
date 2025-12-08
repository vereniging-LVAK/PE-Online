<?php

namespace PeOnline;

/**
 * Attendance request object for attendance submission
 */
class AttendanceRequest
{
    private int $orgID;
    private ?int $PECourseID = null;
    private ?int $PEEditionID = null;
    private ?string $externalCourseId = null;
    private ?int $pePersonId = null;
    private ?string $externalPersonId = null;
    private ?string $endDate;
    private ?int $peModuleId = null;
    private ?string $externalModuleId = null;

    public function __construct(
        int $orgID,
        ?string $endDate = null,
        ?int $PECourseID = null,
        ?int $PEEditionID = null,
        ?string $externalCourseId = null,
        ?int $pePersonId = null,
        ?string $externalPersonId = null,
        ?int $peModuleId = null,
        ?string $externalModuleId = null
    ) {
        $this->orgID = $orgID;
        $this->endDate = $endDate;
        $this->PECourseID = $PECourseID;
        $this->PEEditionID = $PEEditionID;
        $this->externalCourseId = $externalCourseId;
        $this->pePersonId = $pePersonId;
        $this->externalPersonId = $externalPersonId;
        $this->peModuleId = $peModuleId;
        $this->externalModuleId = $externalModuleId;
    }

    public function getOrgID(): int
    {
        return $this->orgID;
    }
    
    public function getPECourseID(): ?int
    {
        return $this->PECourseID;
    }

    public function getPEEditionID(): ?int
    {
        return $this->PEEditionID;
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

    public function getEndDate(): ?string
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
            'endDate' => $this->endDate,
        ];

        if ($this->PECourseID !== null) {
            $data['PECourseID'] = $this->PECourseID;
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