<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/reservations', name: 'api_reservations')]
class ReservationController extends AbstractController
{
    public function __construct(
        private ReservationService $reservationService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create a new reservation
     */
    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        #[CurrentUser] User $user
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['carId']) || !isset($data['startDate']) || !isset($data['endDate'])) {
            return $this->json([
                'success' => false,
                'message' => 'Missing required fields: carId, startDate, endDate'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $startDate = new \DateTime($data['startDate']);
            $endDate = new \DateTime($data['endDate']);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid date format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->reservationService->createReservation(
            $data['carId'],
            $startDate,
            $endDate,
            $user
        );


        if ($result['success']) {
            return $this->json($result, RESPONSE::HTTP_CREATED);
        }

        return $this->json([
            'success' => false,
            'message' => $result['message']
        ], RESPONSE::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * Get reservations for a specific user
     */
    #[Route('/api/users/{id}/reservations', name: 'api_user_reservations', methods: ['GET'])]
    public function userReservations(
        int $id,
        #[CurrentUser] User $currentUser
    ): JsonResponse {
        if ($currentUser->getId() !== $id) {
            return $this->json([
                'success' => false,
                'message' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $reservations = $this->reservationService->getUserReservations($currentUser);

        return $this->json([
            'success' => true,
            'reservations' => $reservations
        ], RESPONSE::HTTP_OK);
    }
}
