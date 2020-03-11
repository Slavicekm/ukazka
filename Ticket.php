<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Nette;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validation;

/**
 * Class Ticket
 * @ORM\Entity(repositoryClass="App\Repository\TicketRepository")
 * @ORM\Table(name="ticket")
 */
class Ticket extends AbstractModel
{
    use Nette\SmartObject;

    const TIPSPORT = 10;
    const CHANCE = 20;
    const FORTUNA = 30;
    const SAZKABET = 40;
    const FOREIGN = 50;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned":true})
     * @ORM\GeneratedValue
     */
    public $id;

    /**
     * @ORM\Column(type="datetime")
     */
    public $createdAt;

    /**
     * @ORM\Column(type="integer", options={"unsigned":true})
     */
    public $bettingShop;

    /**
     * @ORM\Column(type="float", options={"unsigned":true})
     */
    public $deposit;

    /**
     * @ORM\Column(type="float", options={"unsigned":true})
     */
    public $finalCourse;

    /**
     * @ORM\Column(type="integer", options={"unsigned":true})
     */
    public $finalState;

    /**
     * @ORM\Column(type="datetime")
     */
    public $closestMatchDate;

    /**
     * @ORM\Column(type="boolean", options={"default": "0"})
     */
    public $checked = false;

    /**
     * @ORM\OneToMany(targetEntity="App\Model\TicketComponent", mappedBy="ticket", cascade={"persist"})
     */
    public $components;

    /**
     * @ORM\ManyToOne(targetEntity="App\Model\Package", inversedBy="tickets")
     * @ORM\JoinColumn(name="package_id", referencedColumnName="id", onDelete="CASCADE")
     */
    public $package;

    /**
     * @ORM\ManyToOne(targetEntity="App\Model\Admin", inversedBy="tickets")
     * @ORM\JoinColumn(name="admin_id", referencedColumnName="id", onDelete="RESTRICT")
     */
    public $admin;

    /**
     * Checks if entity is valid
     * @return array
     */
    public function validate(): array
    {
        $validator = Validation::createValidator();
        $violations = [];

        $violations[] = $validator->validate($this->bettingShop, [
            new Constraints\NotNull([
                'message' => 'Musíte zadat název sázkové kanceláře',
            ]),
        ]);
        $violations[] = $validator->validate(intval($this->bettingShop), [
            new Constraints\Choice([
                'choices' => array_keys(self::getBettingShopOptions()),
                'message' => 'Musíte zadat správnou sázkovou kancelář',
            ]),
        ]);

        $violations[] = $validator->validate($this->deposit, [
            new Constraints\NotNull([
                'message' => 'Musíte zadat vklad',
            ]),
        ]);

        $violations[] = $validator->validate($this->deposit, [
            new Constraints\Positive([
                'message' => 'Vklad musí být kladné číslo',
            ]),
        ]);

        $violations[] = $validator->validate($this->finalCourse, [
            new Constraints\Positive([
                'message' => 'Kurz musí být kladné číslo',
            ]),
        ]);

        return $violations;
    }

    /**
     * Gets betting shops options
     *
     * @return array
     */
    public static function getBettingShopOptions(): array
    {
        return [
            self::TIPSPORT => 'Tipsport',
            self::CHANCE => 'Chance',
            self::FORTUNA => 'Fortuna',
            self::SAZKABET => 'Sazkabet',
            self::FOREIGN => 'Zahraniční',
        ];
    }

    /**
     * Gets betting shop name
     *
     * @return string
     */
    public function getBettingShopName(): string
    {
        $bettingShops = self::getBettingShopOptions();
        if (isset($bettingShops[$this->bettingShop])) {
            return $bettingShops[$this->bettingShop];
        }

        return '';
    }



    /**
     * Gets final course of all components
     *
     * @return float
     */
    public function getFinalCourse(): float
    {
        return floatval(number_format((float)$this->finalCourse, 2, '.', ''));
    }

    /**
     * Gets final state
     *
     * @return array
     */
    public function getFinalState(): array
    {
        $stateOptions = TicketComponent::getStateOptions();
        $stateClasses = TicketComponent::getStateClasses();

        return [
            'name' => $stateOptions[$this->finalState],
            'class' => $stateClasses[$this->finalState],
            'value' => $this->finalState,
        ];
    }

    /**
     * @return string
     */
    public function getFrontendStateClass(): string
    {
        return TicketComponent::getFrontendStateClasses()[$this->finalState];
    }

    /**
     * Gets date of first match in ticket
     *
     * @return DateTime
     */
    public function getClosestMatchDate(): DateTime
    {
        return $this->closestMatchDate;
    }

    /**
     * Returns count of components
     *
     * @return integer
     */
    public function getMatchCount(): int
    {
        return count($this->components);
    }

    /**
     * @return float
     */
    public function getProfit(): float
    {
        if ($this->finalState === TicketComponent::STATE_SUCCESS) {
            return ($this->deposit * $this->finalCourse) - $this->deposit;
        } elseif ($this->finalState === TicketComponent::STATE_FAIL) {
            return -1 * $this->deposit;
        } else {
            return 0;
        }
    }
}
