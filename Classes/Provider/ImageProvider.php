<?php

namespace Lfda\Headless\Provider;


use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Service\ImageService;

class ImageProvider extends BaseProvider
{

    public function fetchData()
    {

        $imageUid = $this->getArgument('uid', 0);
        if ($imageUid <= 0) {
            return NULL;
        }

        $imageService = GeneralUtility::makeInstance(ImageService::class);
        $image = $imageService->getImage($imageUid, null, true);
        $processingInstructions = [];

        $crop = $this->getArgument('crop');
        $image_crop = $image->hasProperty('crop') ?: NULL;

        if ($crop || $image_crop) {
            $cropString = $image_crop;

            if (!$cropString) {
                list($x, $y, $w, $h) = GeneralUtility::trimExplode(',', $crop);

                $cropString = json_encode([
                    'default' => [
                        'cropArea' => [
                            'x' => floatval($x),
                            'y' => floatval($y),
                            'width' => floatval($w),
                            'height' => floatval($h)
                        ]
                    ]
                ]);
            }

            if ($cropString !== NULL) {
                $cropVariantCollection = CropVariantCollection::create($cropString);
                $cropVariant = $_REQUEST['cropVariant'] ?: 'default';
                $cropArea = $cropVariantCollection->getCropArea($cropVariant);

                $processingInstructions['crop'] = $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image);
            }
        }

        if ($this->getArgument('width') > 0) {
            $processingInstructions['maxWidth'] = $this->getArgument('width');
        }

        if ($this->getArgument('height') > 0) {
            $processingInstructions['maxHeight'] = $this->getArgument('height');
        }

        return $imageService->applyProcessingInstructions($image, $processingInstructions);
    }

}
