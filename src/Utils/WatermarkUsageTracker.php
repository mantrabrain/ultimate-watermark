<?php

namespace MantraBrain\UltimateWatermark\Utils;

/**
 * Watermark Usage Tracker
 * 
 * Tracks watermark usage counts and which watermarks are applied to which images
 */
class WatermarkUsageTracker
{
    /**
     * Increment watermark usage count and log image usage
     * 
     * @param int $watermark_id Watermark ID
     * @param int $attachment_id Image attachment ID
     * @param string $image_size Image size (e.g., 'full', 'large', 'medium', 'thumbnail')
     * @return bool Success status
     */
    public static function incrementUsage(int $watermark_id, int $attachment_id, string $image_size = 'full'): bool
    {
        try {
            // Increment watermark usage count
            $current_count = get_post_meta($watermark_id, 'watermark_usage_count', true) ?: 0;
            update_post_meta($watermark_id, 'watermark_usage_count', $current_count + 1);
            
            // Log this image as using this watermark
            $used_images = get_post_meta($watermark_id, 'watermark_used_images', true) ?: [];
            if (!in_array($attachment_id, $used_images)) {
                $used_images[] = $attachment_id;
                update_post_meta($watermark_id, 'watermark_used_images', $used_images);
            }
            
            // Log this watermark as applied to this image
            $applied_watermarks = get_post_meta($attachment_id, 'applied_watermarks', true) ?: [];
            if (!in_array($watermark_id, $applied_watermarks)) {
                $applied_watermarks[] = $watermark_id;
                update_post_meta($attachment_id, 'applied_watermarks', $applied_watermarks);
            }
            
            // Track watermarks per size
            $size_watermarks = get_post_meta($attachment_id, 'watermarks_by_size', true) ?: [];
            if (!isset($size_watermarks[$image_size])) {
                $size_watermarks[$image_size] = [];
            }
            if (!in_array($watermark_id, $size_watermarks[$image_size])) {
                $size_watermarks[$image_size][] = $watermark_id;
                update_post_meta($attachment_id, 'watermarks_by_size', $size_watermarks);
            }
            
            // Update image watermark count
            $image_watermark_count = get_post_meta($attachment_id, 'watermark_count', true) ?: 0;
            update_post_meta($attachment_id, 'watermark_count', $image_watermark_count + 1);
            
            // Track watermark application date
            $current_date = current_time('Y-m-d');
            $watermark_dates = get_post_meta($attachment_id, 'watermark_application_dates', true) ?: [];
            if (!in_array($current_date, $watermark_dates)) {
                $watermark_dates[] = $current_date;
                update_post_meta($attachment_id, 'watermark_application_dates', $watermark_dates);
            }
            
            // Invalidate analytics cache to reflect changes immediately
            if (function_exists('delete_transient')) {
                $timeframes = ['1','7','30','90','365'];
                $offsets = ['0','1']; // today/yesterday support
                foreach ($timeframes as $tf) {
                    foreach ($offsets as $off) {
                        delete_transient('uw_analytics_' . $tf . '_' . $off);
                    }
                }
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error incrementing usage for watermark ' . $watermark_id . ' on image ' . $attachment_id . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrement watermark usage count and remove image usage log
     * 
     * @param int $watermark_id Watermark ID
     * @param int $attachment_id Image attachment ID
     * @return bool Success status
     */
    public static function decrementUsage(int $watermark_id, int $attachment_id): bool
    {
        try {
            // Decrement watermark usage count (don't go below 0)
            $current_count = get_post_meta($watermark_id, 'watermark_usage_count', true) ?: 0;
            if ($current_count > 0) {
                update_post_meta($watermark_id, 'watermark_usage_count', $current_count - 1);
            }
            
            // Remove this image from watermark's used images list
            $used_images = get_post_meta($watermark_id, 'watermark_used_images', true) ?: [];
            $used_images = array_values(array_filter($used_images, function($id) use ($attachment_id) {
                return $id != $attachment_id;
            }));
            update_post_meta($watermark_id, 'watermark_used_images', $used_images);
            
            // Remove this watermark from image's applied watermarks list
            $applied_watermarks = get_post_meta($attachment_id, 'applied_watermarks', true) ?: [];
            $applied_watermarks = array_values(array_filter($applied_watermarks, function($id) use ($watermark_id) {
                return $id != $watermark_id;
            }));
            update_post_meta($attachment_id, 'applied_watermarks', $applied_watermarks);
            
            // Update image watermark count (don't go below 0)
            $image_watermark_count = get_post_meta($attachment_id, 'watermark_count', true) ?: 0;
            if ($image_watermark_count > 0) {
                update_post_meta($attachment_id, 'watermark_count', $image_watermark_count - 1);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error decrementing usage for watermark ' . $watermark_id . ' on image ' . $attachment_id . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get watermark usage count
     * 
     * @param int $watermark_id Watermark ID
     * @return int Usage count
     */
    public static function getUsageCount(int $watermark_id): int
    {
        return (int) get_post_meta($watermark_id, 'watermark_usage_count', true);
    }
    
    /**
     * Get images using this watermark
     * 
     * @param int $watermark_id Watermark ID
     * @return array Array of attachment IDs
     */
    public static function getUsedImages(int $watermark_id): array
    {
        return get_post_meta($watermark_id, 'watermark_used_images', true) ?: [];
    }
    
    /**
     * Get watermarks applied to an image
     * 
     * @param int $attachment_id Image attachment ID
     * @return array Array of watermark IDs
     */
    public static function getAppliedWatermarks(int $attachment_id): array
    {
        return get_post_meta($attachment_id, 'applied_watermarks', true) ?: [];
    }
    
    /**
     * Get watermarks applied to specific image size
     * 
     * @param int $attachment_id Image attachment ID
     * @param string $image_size Image size (e.g., 'full', 'large', 'medium', 'thumbnail')
     * @return array Array of watermark IDs for the specific size
     */
    public static function getWatermarksBySize(int $attachment_id, string $image_size): array
    {
        $size_watermarks = get_post_meta($attachment_id, 'watermarks_by_size', true) ?: [];
        return $size_watermarks[$image_size] ?? [];
    }
    
    /**
     * Get all watermarks by size for an image
     * 
     * @param int $attachment_id Image attachment ID
     * @return array Array with size as key and watermark IDs as values
     */
    public static function getAllWatermarksBySize(int $attachment_id): array
    {
        return get_post_meta($attachment_id, 'watermarks_by_size', true) ?: [];
    }
    
    /**
     * Get watermark count for an image
     * 
     * @param int $attachment_id Image attachment ID
     * @return int Watermark count
     */
    public static function getImageWatermarkCount(int $attachment_id): int
    {
        return (int) get_post_meta($attachment_id, 'watermark_count', true);
    }
    
    /**
     * Get detailed usage statistics for a watermark
     * 
     * @param int $watermark_id Watermark ID
     * @return array Usage statistics
     */
    public static function getUsageStats(int $watermark_id): array
    {
        $usage_count = self::getUsageCount($watermark_id);
        $used_images = self::getUsedImages($watermark_id);
        
        // Get image details
        $image_details = [];
        foreach ($used_images as $attachment_id) {
            $attachment = get_post($attachment_id);
            if ($attachment) {
                $image_details[] = [
                    'id' => $attachment_id,
                    'title' => $attachment->post_title,
                    'url' => wp_get_attachment_url($attachment_id),
                    'thumbnail' => wp_get_attachment_image_url($attachment_id, 'thumbnail'),
                    'date' => $attachment->post_date
                ];
            }
        }
        
        return [
            'usage_count' => $usage_count,
            'used_images' => $used_images,
            'image_details' => $image_details,
            'unique_images' => count($used_images)
        ];
    }
    
    /**
     * Get detailed statistics for an image
     * 
     * @param int $attachment_id Image attachment ID
     * @return array Image statistics
     */
    public static function getImageStats(int $attachment_id): array
    {
        $watermark_count = self::getImageWatermarkCount($attachment_id);
        $applied_watermarks = self::getAppliedWatermarks($attachment_id);
        
        // Get watermark details
        $watermark_details = [];
        foreach ($applied_watermarks as $watermark_id) {
            $watermark = get_post($watermark_id);
            if ($watermark) {
                $watermark_details[] = [
                    'id' => $watermark_id,
                    'name' => $watermark->post_title,
                    'type' => get_post_meta($watermark_id, 'watermark_type', true),
                    'created' => $watermark->post_date
                ];
            }
        }
        
        return [
            'watermark_count' => $watermark_count,
            'applied_watermarks' => $applied_watermarks,
            'watermark_details' => $watermark_details
        ];
    }
    
    /**
     * Clean up usage data when watermark is deleted
     * 
     * @param int $watermark_id Watermark ID
     * @return bool Success status
     */
    public static function cleanupWatermarkUsage(int $watermark_id): bool
    {
        try {
            $used_images = self::getUsedImages($watermark_id);
            
            // Remove watermark from all images' applied watermarks lists
            foreach ($used_images as $attachment_id) {
                $applied_watermarks = get_post_meta($attachment_id, 'applied_watermarks', true) ?: [];
                $applied_watermarks = array_values(array_filter($applied_watermarks, function($id) use ($watermark_id) {
                    return $id != $watermark_id;
                }));
                update_post_meta($attachment_id, 'applied_watermarks', $applied_watermarks);
                
                // Decrement image watermark count
                $image_watermark_count = get_post_meta($attachment_id, 'watermark_count', true) ?: 0;
                if ($image_watermark_count > 0) {
                    update_post_meta($attachment_id, 'watermark_count', $image_watermark_count - 1);
                }
            }
            
            // Remove watermark usage data
            delete_post_meta($watermark_id, 'watermark_usage_count');
            delete_post_meta($watermark_id, 'watermark_used_images');
            
            return true;
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error cleaning up usage for watermark ' . $watermark_id . ': ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up usage data when image is deleted
     * 
     * @param int $attachment_id Image attachment ID
     * @return bool Success status
     */
    public static function cleanupImageUsage(int $attachment_id): bool
    {
        try {
            $applied_watermarks = self::getAppliedWatermarks($attachment_id);
            
            // Decrement usage count for all applied watermarks
            foreach ($applied_watermarks as $watermark_id) {
                $current_count = get_post_meta($watermark_id, 'watermark_usage_count', true) ?: 0;
                if ($current_count > 0) {
                    update_post_meta($watermark_id, 'watermark_usage_count', $current_count - 1);
                }
                
                // Remove image from watermark's used images list
                $used_images = get_post_meta($watermark_id, 'watermark_used_images', true) ?: [];
                $used_images = array_values(array_filter($used_images, function($id) use ($attachment_id) {
                    return $id != $attachment_id;
                }));
                update_post_meta($watermark_id, 'watermark_used_images', $used_images);
            }
            
            // Remove image usage data
            delete_post_meta($attachment_id, 'applied_watermarks');
            delete_post_meta($attachment_id, 'watermark_count');
            
            return true;
        } catch (\Exception $e) {
            error_log('Ultimate Watermark: Error cleaning up usage for image ' . $attachment_id . ': ' . $e->getMessage());
            return false;
        }
    }
}
