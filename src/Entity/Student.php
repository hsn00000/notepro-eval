<?php

namespace App\Entity;

use App\Repository\StudentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Subject;

#[ORM\Entity(repositoryClass: StudentRepository::class)]
class Student extends User
{
    #[ORM\OneToMany(mappedBy: 'student', targetEntity: Grade::class, orphanRemoval: true)]
    private Collection $grades;

    #[ORM\ManyToOne(fetch: 'EAGER', inversedBy: 'students')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ClassLevel $classLevel = null;

    public function __construct()
    {
        parent::__construct();
        $this->grades = new ArrayCollection();
    }

    /**
     * @return Collection<int, Grade>
     */
    public function getGrades(): Collection
    {
        return $this->grades;
    }

    public function addGrade(Grade $grade): static
    {
        if (!$this->grades->contains($grade)) {
            $this->grades->add($grade);
            $grade->setStudent($this);
        }

        return $this;
    }

    public function removeGrade(Grade $grade): static
    {
        if ($this->grades->removeElement($grade)) {
            // set the owning side to null (unless already changed)
            if ($grade->getStudent() === $this) {
                $grade->setStudent(null);
            }
        }

        return $this;
    }

    public function getClassLevel(): ?ClassLevel
    {
        return $this->classLevel;
    }

    public function setClassLevel(?ClassLevel $classLevel): static
    {
        $this->classLevel = $classLevel;

        return $this;
    }

    public function getGradeByEval (Evaluation $evaluation): ?Grade
    {
        foreach ($this->getGrades() as $grade){
            if ($grade->getEvaluation() === $evaluation){
                return $grade;
            }
        }
        return null;
    }

    public function getAverageBySubject(Subject $subject): ?float
    {
        $total = 0;
        $count = 0;

        foreach ($this->grades as $grade) {
            $evaluation = $grade->getEvaluation();

            // 1. Vérifier si la note concerne la bonne matière
            if ($evaluation->getSubject() !== $subject) {
                continue;
            }

            // 2. Sécurité US 2 : Vérifier la date de publication
            // (Uniquement si la méthode existe et que la date est définie)
            if (method_exists($evaluation, 'getPublishDate') &&
                $evaluation->getPublishDate() &&
                $evaluation->getPublishDate() > new \DateTime()) {
                continue;
            }

            // 3. Récupérer la note brute
            $valeurNote = $grade->getGrade();

            // Si la note est nulle (élève absent ou pas encore noté), on l'ignore
            // Cette vérification suffit et permet de passer le test unitaire
            if ($valeurNote === null) {
                continue;
            }

            // 4. Calcul et Normalisation sur 20
            $bareme = $evaluation->getBareme();

            // Évite la division par zéro
            if ($bareme > 0) {
                // Règle de trois pour ramener la note sur 20
                $noteSur20 = ($valeurNote / $bareme) * 20;

                $total += $noteSur20;
                $count++;
            }
        }

        // Si aucune note n'a été retenue, pas de moyenne
        if ($count === 0) {
            return null;
        }

        return $total / $count;
    }

}
