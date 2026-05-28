<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Doctrine;

use App\Inventory\Domain\Exception\ConcurrentPurchaseOrderModification;
use App\Inventory\Domain\Exception\PurchaseOrderNotFound;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\PurchaseOrderStatus;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\VendorId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\QueryBuilder;

/**
 * Doctrine adapter for the {@see PurchaseOrders} port. The only class
 * in src/Inventory/.../PurchaseOrder/ allowed to import
 * {@see EntityManagerInterface} (enforced by Deptrac).
 */
final class DoctrinePurchaseOrders implements PurchaseOrders
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function add(PurchaseOrder $order): void
    {
        $this->em->persist($order);
        $this->em->flush();
    }

    public function save(PurchaseOrder $order): void
    {
        if (! $this->em->contains($order)) {
            throw PurchaseOrderNotFound::withId($order->id());
        }

        try {
            $this->em->flush();
        } catch (OptimisticLockException $e) {
            throw ConcurrentPurchaseOrderModification::forPurchaseOrder($order->id(), $e);
        }
    }

    public function byId(PurchaseOrderId $id): PurchaseOrder
    {
        $order = $this->em->find(PurchaseOrder::class, $id);

        if (! $order instanceof PurchaseOrder) {
            throw PurchaseOrderNotFound::withId($id);
        }

        return $order;
    }

    public function openByVendor(VendorId $vendorId, int $offset, int $limit): array
    {
        /** @var list<PurchaseOrder> */
        return $this->em->createQueryBuilder()
            ->select('po')
            ->from(PurchaseOrder::class, 'po')
            ->where('po.vendorId = :vendor')
            ->andWhere('po.status IN (:open)')
            ->setParameter('vendor', $vendorId)
            ->setParameter('open', [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])
            ->orderBy('po.createdAt', 'DESC')
            ->addOrderBy('po.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function byStatus(PurchaseOrderStatus $status, int $offset, int $limit): array
    {
        /** @var list<PurchaseOrder> */
        return $this->em->createQueryBuilder()
            ->select('po')
            ->from(PurchaseOrder::class, 'po')
            ->where('po.status = :status')
            ->setParameter('status', $status)
            ->orderBy('po.createdAt', 'DESC')
            ->addOrderBy('po.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function byFacility(FacilityCode $facility, int $offset, int $limit): array
    {
        /** @var list<PurchaseOrder> */
        return $this->em->createQueryBuilder()
            ->select('po')
            ->from(PurchaseOrder::class, 'po')
            ->where('po.facilityCode = :facility')
            ->setParameter('facility', $facility)
            ->orderBy('po.createdAt', 'DESC')
            ->addOrderBy('po.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function search(
        ?VendorId $vendorId,
        ?PurchaseOrderStatus $status,
        ?FacilityCode $facility,
        int $offset,
        int $limit,
    ): array {
        $qb = $this->em->createQueryBuilder()
            ->select('po')
            ->from(PurchaseOrder::class, 'po')
            ->orderBy('po.createdAt', 'DESC')
            ->addOrderBy('po.id', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $this->applyFilters($qb, $vendorId, $status, $facility);

        /** @var list<PurchaseOrder> */
        return $qb->getQuery()->getResult();
    }

    public function countMatching(
        ?VendorId $vendorId,
        ?PurchaseOrderStatus $status,
        ?FacilityCode $facility,
    ): int {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(po.id)')
            ->from(PurchaseOrder::class, 'po');

        $this->applyFilters($qb, $vendorId, $status, $facility);

        /** @var int|string|null $result */
        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) $result;
    }

    private function applyFilters(
        QueryBuilder $qb,
        ?VendorId $vendorId,
        ?PurchaseOrderStatus $status,
        ?FacilityCode $facility,
    ): void {
        if ($vendorId !== null) {
            $qb->andWhere('po.vendorId = :vendor')->setParameter('vendor', $vendorId);
        }
        if ($status !== null) {
            $qb->andWhere('po.status = :status')->setParameter('status', $status);
        }
        if ($facility !== null) {
            $qb->andWhere('po.facilityCode = :facility')->setParameter('facility', $facility);
        }
    }
}
