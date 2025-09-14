import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/foundation.dart';
import 'package:flutter_image_compress/flutter_image_compress.dart';
import 'package:path_provider/path_provider.dart';
import 'package:path/path.dart' as path;

class ImageCompressionService {
  /// Compress image file to reduce size and improve upload performance
  static Future<File?> compressImageFile(File imageFile) async {
    try {
      // Get temporary directory
      final tempDir = await getTemporaryDirectory();
      final targetPath = path.join(
        tempDir.path,
        'compressed_${DateTime.now().millisecondsSinceEpoch}.jpg',
      );

      // Compress image with optimized settings
      final compressedFile = await FlutterImageCompress.compressAndGetFile(
        imageFile.absolute.path,
        targetPath,
        quality: 75, // 75% quality - good balance between size and quality
        minWidth: 800, // Max width 800px
        minHeight: 600, // Max height 600px
        format: CompressFormat.jpeg, // Use JPEG for better compression
        keepExif: false, // Remove EXIF data to reduce size
      );

      if (compressedFile != null) {
        // Check if compression actually reduced file size
        final originalSize = await imageFile.length();
        final compressedSize = await compressedFile.length();
        
        debugPrint('Image compression: ${originalSize} -> ${compressedSize} bytes (${((1 - compressedSize/originalSize) * 100).toStringAsFixed(1)}% reduction)');
        
        return File(compressedFile.path);
      }
      
      return null;
    } catch (e) {
      debugPrint('Error compressing image: $e');
      return null;
    }
  }

  /// Compress image bytes (for web)
  static Future<Uint8List?> compressImageBytes(Uint8List imageBytes) async {
    try {
      // Compress image bytes with optimized settings
      final compressedBytes = await FlutterImageCompress.compressWithList(
        imageBytes,
        quality: 75, // 75% quality
        minWidth: 800, // Max width 800px
        minHeight: 600, // Max height 600px
        format: CompressFormat.jpeg, // Use JPEG for better compression
        keepExif: false, // Remove EXIF data to reduce size
      );

      if (compressedBytes.isNotEmpty) {
        debugPrint('Image bytes compression: ${imageBytes.length} -> ${compressedBytes.length} bytes (${((1 - compressedBytes.length/imageBytes.length) * 100).toStringAsFixed(1)}% reduction)');
        return compressedBytes;
      }
      
      return null;
    } catch (e) {
      debugPrint('Error compressing image bytes: $e');
      return null;
    }
  }

}
