<?php
# Generated by the protocol buffer compiler.  DO NOT EDIT!
# source: google/photos/types/media_item.proto

namespace GPBMetadata\Google\Photos\Types;

class MediaItem
{
    public static $is_initialized = false;

    public static function initOnce() {
        $pool = \Google\Protobuf\Internal\DescriptorPool::getGeneratedPool();

        if (static::$is_initialized == true) {
          return;
        }
        \GPBMetadata\Google\Protobuf\Duration::initOnce();
        \GPBMetadata\Google\Protobuf\Timestamp::initOnce();
        $pool->internalAddGeneratedFile(
            '
�
$google/photos/types/media_item.protogoogle.photos.typesgoogle/protobuf/timestamp.proto"�
	MediaItem

id (	
description (	
product_url (	
base_url (	
	mime_type (	:
media_metadata (2".google.photos.types.MediaMetadata>
contributor_info (2$.google.photos.types.ContributorInfo
filename (	"�
MediaMetadata1
creation_time (2.google.protobuf.Timestamp
width (
height (+
photo (2.google.photos.types.PhotoH +
video (2.google.photos.types.VideoH B

metadata"�
Photo
camera_make (	
camera_model (	
focal_length (
aperture_f_number (
iso_equivalent (0
exposure_time (2.google.protobuf.Duration"{
Video
camera_make (	
camera_model (	
fps (:
status (2*.google.photos.types.VideoProcessingStatus"I
ContributorInfo 
profile_picture_base_url (	
display_name (	*O
VideoProcessingStatus
UNSPECIFIED 

PROCESSING	
READY

FAILEDBk
com.google.photos.types.protoBMediaItemProtoPZ8google.golang.org/genproto/googleapis/photos/types;typesbproto3'
        , true);

        static::$is_initialized = true;
    }
}

