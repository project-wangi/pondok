<?php

namespace App\Filament\Exports;

use DOMDocument;
use DOMElement;
use RuntimeException;
use Throwable;
use ZipArchive;

class JadwalPelajaranLogo
{
    private const SPREADSHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing';

    private const OFFICE_REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    private const CONTENT_TYPES_NS = 'http://schemas.openxmlformats.org/package/2006/content-types';

    public function embed(string $xlsxPath, string $imagePath): void
    {
        if (! is_file($imagePath) || ! is_readable($imagePath)) {
            return;
        }

        $image = @file_get_contents($imagePath);
        $dimensions = $image === false ? false : @getimagesizefromstring($image);

        if ($dimensions === false || $dimensions[0] < 1 || $dimensions[1] < 1) {
            return;
        }

        if ($dimensions[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromstring')) {
            $resource = @imagecreatefromstring($image);

            if ($resource !== false) {
                ob_start();

                try {
                    imagepng($resource);
                    $image = (string) ob_get_contents();
                    $dimensions[2] = IMAGETYPE_PNG;
                } finally {
                    ob_end_clean();
                    imagedestroy($resource);
                }
            }
        }

        $extension = match ($dimensions[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_JPEG => 'jpeg',
            default => null,
        };

        if ($extension === null) {
            return;
        }

        $scale = min(1, 85 / $dimensions[0], 75 / $dimensions[1]);
        $width = max(1, (int) round($dimensions[0] * $scale * 9525));
        $height = max(1, (int) round($dimensions[1] * $scale * 9525));
        $zip = new ZipArchive;
        $result = $zip->open($xlsxPath, ZipArchive::CHECKCONS);

        if ($result !== true) {
            throw new RuntimeException("Unable to open XLSX archive [{$xlsxPath}]: ZIP error {$result}.");
        }

        try {
            $parts = $this->prepareParts($zip, $image, $extension, $width, $height);

            foreach ($parts as $path => $contents) {
                if (! $zip->addFromString($path, $contents)) {
                    throw new RuntimeException("Unable to write XLSX part [{$path}]: {$zip->getStatusString()}.");
                }
            }
        } catch (Throwable $exception) {
            $zip->unchangeAll();
            $zip->close();

            throw $exception;
        }

        if (! $zip->close()) {
            throw new RuntimeException("Unable to save XLSX archive [{$xlsxPath}].");
        }
    }

    /**
     * @return array<string, string>
     */
    private function prepareParts(ZipArchive $zip, string $image, string $extension, int $width, int $height): array
    {
        $contentTypes = $this->readXml($zip, '[Content_Types].xml', self::CONTENT_TYPES_NS, 'Types');
        $worksheets = [];

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $path = $zip->getNameIndex($index);

            if ($path === false) {
                throw new RuntimeException('Unable to read XLSX archive directory.');
            }

            if (preg_match('~^xl/worksheets/[^/]+\.xml$~', $path) === 1) {
                $worksheets[] = $path;
            }
        }

        if ($worksheets === []) {
            throw new RuntimeException('The XLSX archive contains no worksheets.');
        }

        $parts = [];
        $imagePath = $this->unusedPath($zip, $parts, 'xl/media/jadwal-logo', ".{$extension}");
        $parts[$imagePath] = $image;
        $this->addContentType($contentTypes, $imagePath, "image/{$extension}");

        foreach ($worksheets as $worksheetPath) {
            $worksheet = $this->readXml($zip, $worksheetPath, self::SPREADSHEET_NS, 'worksheet');

            if ($worksheet->getElementsByTagNameNS(self::SPREADSHEET_NS, 'drawing')->length > 0) {
                throw new RuntimeException("Worksheet [{$worksheetPath}] already contains a drawing.");
            }

            $relationshipsPath = dirname($worksheetPath).'/_rels/'.basename($worksheetPath).'.rels';
            $relationships = $zip->locateName($relationshipsPath) === false
                ? $this->newXml(self::PACKAGE_REL_NS, 'Relationships')
                : $this->readXml($zip, $relationshipsPath, self::PACKAGE_REL_NS, 'Relationships');
            $drawingPath = $this->unusedPath($zip, $parts, 'xl/drawings/jadwal-logo', '.xml');
            $drawingRelationshipsPath = 'xl/drawings/_rels/'.basename($drawingPath).'.rels';
            $relationshipId = $this->nextRelationshipId($relationships);

            $this->addRelationship($relationships, $relationshipId, 'drawing', '../drawings/'.basename($drawingPath));
            $this->attachDrawing($worksheet, $relationshipId);

            $drawingRelationships = $this->newXml(self::PACKAGE_REL_NS, 'Relationships');
            $this->addRelationship($drawingRelationships, 'rId1', 'image', '../media/'.basename($imagePath));
            $this->addContentType($contentTypes, $drawingPath, 'application/vnd.openxmlformats-officedocument.drawing+xml');

            $parts[$worksheetPath] = $this->serialize($worksheet);
            $parts[$relationshipsPath] = $this->serialize($relationships);
            $parts[$drawingPath] = $this->drawingXml($width, $height);
            $parts[$drawingRelationshipsPath] = $this->serialize($drawingRelationships);
        }

        $parts['[Content_Types].xml'] = $this->serialize($contentTypes);

        return $parts;
    }

    /**
     * @param  array<string, string>  $parts
     */
    private function unusedPath(ZipArchive $zip, array $parts, string $prefix, string $suffix): string
    {
        $index = 1;

        do {
            $path = $prefix.$index++.$suffix;
            $relationshipsPath = dirname($path).'/_rels/'.basename($path).'.rels';
        } while ($zip->locateName($path) !== false || isset($parts[$path])
            || $zip->locateName($relationshipsPath) !== false || isset($parts[$relationshipsPath]));

        return $path;
    }

    private function readXml(ZipArchive $zip, string $path, string $namespace, string $root): DOMDocument
    {
        $contents = $zip->getFromName($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read XLSX part [{$path}].");
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadXML($contents, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $document->doctype !== null
            || $document->documentElement?->namespaceURI !== $namespace
            || $document->documentElement?->localName !== $root) {
            throw new RuntimeException("Invalid XML in XLSX part [{$path}].");
        }

        return $document;
    }

    private function newXml(string $namespace, string $root): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->appendChild($document->createElementNS($namespace, $root));

        return $document;
    }

    private function serialize(DOMDocument $document): string
    {
        $xml = $document->saveXML();

        if ($xml === false) {
            throw new RuntimeException('Unable to serialize XLSX XML.');
        }

        return $xml;
    }

    private function nextRelationshipId(DOMDocument $relationships): string
    {
        $usedIds = [];

        foreach ($relationships->getElementsByTagNameNS(self::PACKAGE_REL_NS, 'Relationship') as $relationship) {
            $usedIds[$relationship->getAttribute('Id')] = true;
        }

        $index = 1;

        while (isset($usedIds['rId'.$index])) {
            $index++;
        }

        return 'rId'.$index;
    }

    private function addRelationship(DOMDocument $document, string $id, string $type, string $target): void
    {
        $relationship = $document->createElementNS(self::PACKAGE_REL_NS, 'Relationship');
        $relationship->setAttribute('Id', $id);
        $relationship->setAttribute('Type', self::OFFICE_REL_NS.'/'.$type);
        $relationship->setAttribute('Target', $target);
        $document->documentElement->appendChild($relationship);
    }

    private function addContentType(DOMDocument $document, string $path, string $contentType): void
    {
        $override = $document->createElementNS(self::CONTENT_TYPES_NS, 'Override');
        $override->setAttribute('PartName', '/'.$path);
        $override->setAttribute('ContentType', $contentType);
        $document->documentElement->appendChild($override);
    }

    private function attachDrawing(DOMDocument $worksheet, string $relationshipId): void
    {
        $drawing = $worksheet->createElementNS(self::SPREADSHEET_NS, 'drawing');
        $drawing->setAttributeNS(self::OFFICE_REL_NS, 'r:id', $relationshipId);
        $followingElements = [
            'legacyDrawing', 'legacyDrawingHF', 'picture', 'oleObjects', 'controls',
            'webPublishItems', 'tableParts', 'extLst',
        ];

        foreach ($worksheet->documentElement->childNodes as $child) {
            if ($child instanceof DOMElement && in_array($child->localName, $followingElements, true)) {
                $worksheet->documentElement->insertBefore($drawing, $child);

                return;
            }
        }

        $worksheet->documentElement->appendChild($drawing);
    }

    private function drawingXml(int $width, int $height): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<xdr:wsDr xmlns:xdr="'.self::DRAWING_NS.'" '
            .'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
            .'xmlns:r="'.self::OFFICE_REL_NS.'">'
            .'<xdr:oneCellAnchor>'
            .'<xdr:from><xdr:col>0</xdr:col><xdr:colOff>0</xdr:colOff>'
            .'<xdr:row>0</xdr:row><xdr:rowOff>0</xdr:rowOff></xdr:from>'
            .'<xdr:ext cx="'.$width.'" cy="'.$height.'"/>'
            .'<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Logo"/>'
            .'<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            .'<xdr:blipFill><a:blip r:embed="rId1"/>'
            .'<a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            .'<xdr:spPr><a:xfrm><a:off x="0" y="0"/>'
            .'<a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
            .'<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
    }
}
