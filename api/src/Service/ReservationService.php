<?php

namespace App\Service;

use App\Entity\Car;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class ReservationService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function createReservation(
        int $carId,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        User $user
    ): array {
        $car = $this->entityManager->getRepository(Car::class)->find($carId);

        if (!$car) {
            return [
                'success' => false,
                'message' => 'Car not found'
            ];
        }

        if ($endDate <= $startDate) {
            return [
                'success' => false,
                'message' => 'End date must be after start date'
            ];
        }

        if (!$this->isCarAvailable($car, $startDate, $endDate)) {
            return [
                'success' => false,
                'message' => 'Car is not available for the selected dates'
            ];
        }

        $reservation = new Reservation();
        $reservation->setCar($car);
        $reservation->setUser($user);
        $reservation->setStartDate($startDate);
        $reservation->setEndDate($endDate);

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        return [
            'success' => true,
            'message' => 'Reservation created successfully',
            'reservation' => $reservation
        ];
    }
    public function isCarAvailable(
        Car $car,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): bool {
        $qb = $this->entityManager->createQueryBuilder();

        $qb->select('COUNT(r.id)')
            ->from(Reservation::class, 'r')
            ->where('r.car = :car')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->andX(
                        'r.startDate <= :startDate',
                        'r.endDate >= :startDate'
                    ),
                    $qb->expr()->andX(
                        'r.startDate <= :endDate',
                        'r.endDate >= :endDate'
                    ),
                    $qb->expr()->andX(
                        'r.startDate >= :startDate',
                        'r.endDate <= :endDate'
                    )
                )
            )
            ->setParameter('car', $car)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        $conflictCount = $qb->getQuery()->getSingleScalarResult();

        return $conflictCount === 0;
    }

    public function getCarReservations(Car $car): array
    {
        return $this->entityManager
            ->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->where('r.car = :car')
            ->setParameter('car', $car)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getUserReservations(User $user): array
    {
        return $this->entityManager
            ->getRepository(Reservation::class)
            ->createQueryBuilder('r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
