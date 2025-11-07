<?php

namespace App\Entity;

use App\Repository\AlbumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: AlbumRepository::class)]
#[Vich\Uploadable]
class Album
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100)]
    private ?string $artist = null;

    #[ORM\Column(length: 30)]
    private ?string $genre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $trackList = null;

    #[Vich\UploadableField(mapping: 'albumCover', fileNameProperty: 'coverName', size: 'imageSize')]
    private ?File $coverFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coverName = null;

    #[ORM\Column(nullable: true)]
    private ?int $imageSize = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?float $averageRating = null;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'album', orphanRemoval: true)]
    private Collection $reviews;

    #[ORM\ManyToOne(inversedBy: 'albumsAdded')]
    private ?User $addedBy = null;

    public function __construct()
    {
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getArtist(): ?string
    {
        return $this->artist;
    }

    public function setArtist(string $artist): static
    {
        $this->artist = $artist;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(string $genre): static
    {
        $this->genre = $genre;

        return $this;
    }

    public function getTrackList(): ?string
    {
        return $this->trackList;
    }

    public function setTrackList(string $trackList): static
    {
        $this->trackList = $trackList;

        return $this;
    }

    public function getCoverFile(): ?File
    {
        return $this->coverFile;
    }

    public function setCoverFile(?File $coverFile = null): void
    {
        $this->coverFile = $coverFile;

        if (null !== $coverFile) {
            // update a field for doctrine to know something has changed
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getCoverName(): ?string
    {
        return $this->coverName;
    }

    public function setCoverName(?string $coverName): void
    {
        $this->coverName = $coverName;
    }
    
    public function getImageSize(): ?int
    {
        return $this->imageSize;
    }

    public function setImageSize(?int $imageSize): void
    {
        $this->imageSize = $imageSize;
    }

    public function getAverageRating(): ?float
    {
        return $this->averageRating;
    }

    public function setAverageRating(?float $averageRating): static
    {
        $this->averageRating = $averageRating;

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setAlbum($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getAlbum() === $this) {
                $review->setAlbum(null);
            }
        }

        return $this;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function setAddedBy(?User $addedBy): static
    {
        $this->addedBy = $addedBy;

        return $this;
    }

    public function calculateAverageRating(): void
    {
        $reviews = $this->getReviews();
        
        if ($reviews->isEmpty()) {
            $this->averageRating = null;
            return;
        }

        $totalRating = 0;
        $reviewCount = 0;

        foreach ($reviews as $review) {
            if ($review->getRating() !== null) {
                $totalRating += $review->getRating();
                $reviewCount++;
            }
        }

        if ($reviewCount > 0) {
            $this->averageRating = round($totalRating / $reviewCount, 1);
        } else {
            $this->averageRating = null;
        }
    }

}
