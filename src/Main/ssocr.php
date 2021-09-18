<?php

namespace Louissu\Main;

use Imagick;
use ImagickPixel;

class SSOCR
{
    protected $image;

    protected $threshold = 0.1;

    protected $scale = 0;

    /**
     *  --0--
     * |3    |4
     * |     |
     *  --1--
     * |5    |6
     * |     |
     *  --2--
     */
    protected $lineNumbers = [
        '2' => [1, 1, 1, 0, 1, 1, 0],
        '3' => [1, 1, 1, 0, 1, 0, 1],
        '4' => [0, 1, 0, 1, 1, 0, 1],
        '5' => [1, 1, 1, 1, 0, 0, 1],
        '6' => [1, 1, 1, 1, 0, 1, 1],
        '7' => [1, 0, 0, 1, 1, 0, 1],
        '8' => [1, 1, 1, 1, 1, 1, 1],
        '9' => [1, 1, 1, 1, 1, 0, 1],
        '0' => [1, 0, 1, 1, 1, 1, 1],
    ];

    public function __construct($image)
    {

        $this->image = $image;
    }

    public function run()
    {
        $this->checkType($this->image);

        $im = new Imagick();
        $im->readImage($this->image);

        $im->setImageType(Imagick::IMGTYPE_GRAYSCALE);
        $im->thresholdimage($this->threshold * Imagick::getQuantum());

        list($width, $height) = $this->getWidthAndHeight($im);

        if ($this->scale) {
            $im->resizeImage(
                $width / $this->scale,
                $height / $this->scale,
                Imagick::FILTER_LANCZOS,
                1
            );
        }

        list($width, $height) = $this->getWidthAndHeight($im);

        $numberPixels = $this->getNumberPixels($im, $width, $height);

        $numberPositions = $this->getXY($numberPixels);

        $im->writeImage('tmp.' . $this->image);

        $result = $this->getResult($numberPositions);

        return $result;
    }

    public function setThreshold(float $threshold)
    {
        $this->threshold = $threshold;

        return $this;
    }

    public function setScale(int $scale)
    {
        $this->scale = $scale;

        return $this;
    }

    private function checkType($file)
    {
        $allowed = ['image/png', 'image/jpeg'];
        $fileType = mime_content_type($file);

        if (!in_array($fileType, $allowed)) {
            throw new \InvalidArgumentException(
                'File type is invalid, only jpg/jpeg/png can be processed.'
            );
        }
    }

    private function getWidthAndHeight($im)
    {
        $size = $im->getImageGeometry();

        return [$size['width'], $size['height']];
    }

    private function getNumberPixels($im, $width, $height)
    {
        $black = new ImagickPixel('#000000');

        $numberPixels = [];
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                $pixel = $im->getImagePixelColor($x, $y);

                if ($pixel->isSimilar($black, .25)) {
                    $numberPixels[] = [
                        'x' => $x,
                        'y' => $y,
                    ];
                }
            }
        }

        return $numberPixels;
    }

    private function getXY($numberPixels)
    {
        $numberIndex = 0;
        $numberPositions = [];

        foreach ($numberPixels as $key => $numberPixel) {
            if ($key == 0) {
                $numberPositions[$numberIndex]['x1'] = $numberPixel['x'];
                continue;
            }

            if (
                ($numberPixel['x'] != $numberPixels[$key - 1]['x']) &&
                ($numberPixel['x'] != $numberPixels[$key - 1]['x'] + 1)
            ) {
                $numberPositions[$numberIndex]['x2'] = $numberPixels[$key - 1]['x'];
                $numberPositions[$numberIndex]['y1'] = 0;
                $numberPositions[$numberIndex]['y2'] = 0;
                $numberIndex++;
                $numberPositions[$numberIndex]['x1'] = $numberPixel['x'];
            }

            if ($key == count($numberPixels) - 1) {
                $numberPositions[$numberIndex]['x2'] = $numberPixel['x'];
                $numberPositions[$numberIndex]['y1'] = 0;
                $numberPositions[$numberIndex]['y2'] = 0;
            }
        }

        foreach ($numberPixels as $numberPixel) {
            foreach ($numberPositions as $key => &$numberPosition) {
                if (
                    $numberPixel['x'] >= $numberPosition['x1'] &&
                    $numberPixel['x'] <= $numberPosition['x2']
                ) {
                    if ($numberPosition['y1'] == 0) {
                        $numberPosition['y1'] = $numberPixel['y'];
                    }

                    if ($numberPixel['y'] <= $numberPosition['y1']) {
                        $numberPosition['y1'] = $numberPixel['y'];
                    }

                    if ($numberPixel['y'] >= $numberPosition['y2']) {
                        $numberPosition['y2'] = $numberPixel['y'];
                    }
                }
            }
            unset($numberPosition);
        }

        return $numberPositions;
    }

    private function getResult($numberPositions)
    {
        $result = '';

        foreach ($numberPositions as $key => $numberPosition) {
            $im = new Imagick();
            $im->readImage('tmp.' . $this->image);

            $im->cropImage(
                $numberPosition['x2'] - $numberPosition['x1'],
                $numberPosition['y2'] - $numberPosition['y1'],
                $numberPosition['x1'],
                $numberPosition['y1']
            );

            $size   = $im->getImageGeometry();
            $width  = $size['width'];
            $height = $size['height'];

            /**
             * Since the algorithm cannot recognize the digit one,
             * a digit that has a width of less than one quarter of it's height is recognized as a one.
             */
            if ($width < $height / 4) {
                $result .= '1';
                break;
            }

            /**
             * A vertical scan is started in the center top pixel of the digit to find the three horizontal segments.
             * Any foreground pixel in the upper third is counted as part of the top segment,
             * those in the second third as part of the middle and those in the last third as part of the bottom segment.
             */
            $numberPixels = $this->getNumberPixels($im, $width, $height);
            $line         = [0, 0, 0, 0, 0, 0, 0];
            $centerTop    = floor($width / 2);
            $thirdHeight  = floor($height / 3);
            $quarterLeft  = floor($height / 4);
            $halfWidth    = floor($width / 2);

            foreach ($numberPixels as $key => $numberPixel) {
                if ($numberPixel['x'] == $centerTop) {
                    if ($numberPixel['y'] <= $thirdHeight) {
                        $line[0] = 1;
                    } elseif ($numberPixel['y'] <= $thirdHeight * 2) {
                        $line[1] = 1;
                    } elseif ($numberPixel['y'] <= $thirdHeight * 3) {
                        $line[2] = 1;
                    }
                }

                if ($numberPixel['y'] == $quarterLeft) {
                    if ($numberPixel['x'] <= $halfWidth) {
                        $line[3] = 1;
                    } elseif ($numberPixel['x'] >= $halfWidth) {
                        $line[4] = 1;
                    }
                }

                if ($numberPixel['y'] == $quarterLeft * 3) {
                    if ($numberPixel['x'] <= $halfWidth) {
                        $line[5] = 1;
                    } elseif ($numberPixel['x'] >= $halfWidth) {
                        $line[6] = 1;
                    }
                }
            }

            foreach ($this->lineNumbers as $key => $lineNumber) {
                if ($line == $lineNumber) {
                    $result .= $key;
                    break;
                }
            }
        }

        unlink('tmp.' . $this->image);
        return $result;
    }
}
