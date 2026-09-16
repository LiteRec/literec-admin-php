<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Http\Controller;

use App\Households\Application\Command\AttachMemberPhoto;
use App\Households\Application\Command\RemoveMemberPhoto;
use App\Households\Domain\Exception\HouseholdNotFound;
use App\Households\Domain\Exception\InvalidHouseholdId;
use App\Households\Domain\Exception\InvalidMemberId;
use App\Households\Domain\Exception\InvalidProfilePhoto;
use App\Households\Domain\Exception\MemberNotFound;
use App\Households\Domain\Exception\MemberPhotoStorageFailed;
use App\Households\Domain\Exception\UnsupportedImageFormat;
use App\Households\Domain\MemberPhotoStorage;
use App\Households\Domain\ValueObject\ImageFormat;
use App\Households\Infrastructure\Http\Form\RemoveMemberPhotoFormType;
use App\Households\Infrastructure\Http\Form\RemoveMemberPhotoInput;
use App\Households\Infrastructure\Http\Form\UploadMemberPhotoFormType;
use App\Households\Infrastructure\Http\Form\UploadMemberPhotoInput;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP adapter for a member's profile photo (LRA-207): streaming the
 * stored file, uploading a new one, and removing the current one. The
 * Profile card's photo block (read mode only — there is no separate edit
 * mode) posts directly to {@see self::upload()} / {@see self::remove()}
 * and swaps `#card-profile-body`; both also emit an out-of-band update of
 * `#member-avatar` so the page header reflects the change without a full
 * page reload.
 */
final class MemberPhotoController extends AbstractController
{
    use DispatchesHouseholdMessages;

    private const string UUID_V7_REGEX
        = '[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';

    private const string VERSION_REGEX = '[0-9a-f]{32}';

    private const string MEMBER_NOT_FOUND_MESSAGE = 'Member not found.';

    private const string PHOTO_NOT_FOUND_MESSAGE = 'Photo not found.';

    private const string GENERIC_PHOTO_UPLOAD_FAILURE = 'The photo could not be saved. Please try again.';

    private const string SELECT_A_PHOTO_MESSAGE = 'Select a photo to upload.';

    private const string HEADER_HX_TRIGGER = 'HX-Trigger';

    private const string HX_TRIGGER_PHOTO_SAVED = 'photoSaved';

    public function __construct(
        private readonly MessageBusInterface $queryBus,
        private readonly MessageBusInterface $commandBus,
        private readonly MemberPhotoStorage $storage,
    ) {
    }

    private function queryBus(): MessageBusInterface
    {
        return $this->queryBus;
    }

    private function commandBus(): MessageBusInterface
    {
        return $this->commandBus;
    }

    /**
     * Streams the stored photo bytes. 404s unless $version matches the
     * member's current photoVersion — this both rejects unknown members
     * and makes a stale/superseded version 404 instead of serving the
     * wrong (or since-removed) file.
     */
    #[Route(
        '/admin/users/{householdId}/{memberId}/photo/{version}',
        name: 'member_photo',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
            'version'     => self::VERSION_REGEX,
        ],
        methods: ['GET'],
    )]
    public function show(string $householdId, string $memberId, string $version): Response
    {
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        $mimeType = $detail->profile->photoMimeType;
        if ($detail->profile->photoVersion !== $version || $mimeType === null) {
            throw $this->createNotFoundException(self::PHOTO_NOT_FOUND_MESSAGE);
        }

        $storageKey = sprintf('%s/%s.%s', $memberId, $version, ImageFormat::fromMimeType($mimeType)->extension());

        try {
            $file = $this->storage->readable($storageKey);
        } catch (MemberPhotoStorageFailed) {
            throw $this->createNotFoundException(self::PHOTO_NOT_FOUND_MESSAGE);
        }

        $response = new BinaryFileResponse($file);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'private, max-age=31536000, immutable');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE);

        return $response;
    }

    #[Route(
        '/admin/users/{householdId}/{memberId}/photo',
        name: 'member_photo_upload',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function upload(string $householdId, string $memberId, Request $request): Response
    {
        $input = new UploadMemberPhotoInput();
        $form = $this->createForm(UploadMemberPhotoFormType::class, $input);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderPhotoResponse(
                $householdId,
                $memberId,
                $this->firstFormErrorMessage($form),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (!$input->photo instanceof UploadedFile) {
            return $this->renderPhotoResponse(
                $householdId,
                $memberId,
                self::SELECT_A_PHOTO_MESSAGE,
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->attachPhoto($householdId, $memberId, $input->photo);
    }

    #[Route(
        '/admin/users/{householdId}/{memberId}/photo/remove',
        name: 'member_photo_remove',
        requirements: [
            'householdId' => self::UUID_V7_REGEX,
            'memberId'    => self::UUID_V7_REGEX,
        ],
        methods: ['POST'],
    )]
    public function remove(string $householdId, string $memberId, Request $request): Response
    {
        $form = $this->createForm(RemoveMemberPhotoFormType::class, new RemoveMemberPhotoInput());
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->renderPhotoResponse(
                $householdId,
                $memberId,
                $this->firstFormErrorMessage($form),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $this->dispatchCommandUnwrapping(new RemoveMemberPhoto($householdId, $memberId));
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        return $this->photoSavedResponse($householdId, $memberId);
    }

    private function attachPhoto(string $householdId, string $memberId, UploadedFile $photo): Response
    {
        try {
            $this->dispatchCommandUnwrapping(new AttachMemberPhoto(
                householdId: $householdId,
                memberId: $memberId,
                sourcePath: $photo->getPathname(),
                mimeType: (string) $photo->getMimeType(),
            ));
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        } catch (UnsupportedImageFormat | InvalidProfilePhoto $exception) {
            return $this->renderPhotoResponse(
                $householdId,
                $memberId,
                $exception->getMessage(),
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }
        // MemberPhotoStorageFailed intentionally propagates uncaught: it
        // represents an infrastructure failure (disk full, permissions),
        // not a user input problem, so it surfaces as a 5xx that stays
        // visible in incident monitoring rather than being swallowed into
        // a misleading 422.

        return $this->photoSavedResponse($householdId, $memberId);
    }

    private function photoSavedResponse(string $householdId, string $memberId): Response
    {
        $response = $this->renderPhotoResponse($householdId, $memberId, null, Response::HTTP_OK);
        $response->headers->set(self::HEADER_HX_TRIGGER, self::HX_TRIGGER_PHOTO_SAVED);

        return $response;
    }

    /**
     * Re-dispatches {@see \App\Households\Application\Query\GetMemberDetail}
     * and renders the Profile card's read-mode body plus an out-of-band
     * `#member-avatar` update, at the given HTTP status. $photoError, when
     * not null, fills the card's persistent photo-form error region.
     */
    private function renderPhotoResponse(
        string $householdId,
        string $memberId,
        ?string $photoError,
        int $status,
    ): Response {
        try {
            $detail = $this->runQuery($householdId, $memberId);
        } catch (MemberNotFound | HouseholdNotFound | InvalidHouseholdId | InvalidMemberId) {
            throw $this->createNotFoundException(self::MEMBER_NOT_FOUND_MESSAGE);
        }

        return $this->render(
            'households/detail/_card_profile_read_with_avatar_oob.html.twig',
            [
                'detail' => $detail,
                'photoError' => $photoError,
            ],
            new Response(null, $status),
        );
    }

    /**
     * @template TData
     *
     * @param FormInterface<TData> $form
     */
    private function firstFormErrorMessage(FormInterface $form): string
    {
        foreach ($form->getErrors(true) as $error) {
            return $error->getMessage();
        }

        return self::GENERIC_PHOTO_UPLOAD_FAILURE;
    }
}
