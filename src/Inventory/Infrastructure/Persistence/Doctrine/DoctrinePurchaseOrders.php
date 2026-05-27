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
        /** @var list<PurchaseOrder> $rows */
        $rows = $this->em->createQueryBuilder()
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

        return $rows;
    }

    public function byStatus(PurchaseOrderStatus $status, int $offset, int $limit): array
    {
        /** @var list<PurchaseOrder> $rows */
        $rows = $this->em->createQueryBuilder()
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

        return $rows;
    }

    public function byFacility(FacilityCode $facility, int $offset, int $limit): array
    {
        /** @var list<PurchaseOrder> $rows */
        $rows = $this->em->createQueryBuilder()
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

        return $rows;
    }
}
