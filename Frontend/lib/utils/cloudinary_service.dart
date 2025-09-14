import 'dart:convert';
import 'dart:io' show File; // Only used on mobile/desktop
import 'dart:typed_data';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'image_compression_service.dart';

class CloudinaryService {
  static Future<Map<String, dynamic>?> uploadImageToCloudinary(File imageFile) async {
    const cloudName = 'dyf9r2al9';
    const uploadPreset = 'Absensi';

    final url = Uri.parse('https://api.cloudinary.com/v1_1/$cloudName/image/upload');

    // Compress image before upload for better performance
    final compressedFile = await ImageCompressionService.compressImageFile(imageFile);
    final fileToUpload = compressedFile ?? imageFile; // Fallback to original if compression fails

    // Mobile/desktop path upload using dart:io (not supported on web)
    final request = http.MultipartRequest('POST', url)
      ..fields['upload_preset'] = uploadPreset
      ..files.add(await http.MultipartFile.fromPath('file', fileToUpload.path));

    final response = await request.send();

    if (response.statusCode == 200) {
      final respStr = await response.stream.bytesToString();
      final data = json.decode(respStr);
      return {
        'url': data['secure_url'],
        'public_id': data['public_id']
      };
    } else {
      print('Upload failed: ${response.statusCode}');
      return null;
    }
  }

  // Web-safe variant: upload using raw bytes (no dart:io)
  static Future<Map<String, dynamic>?> uploadImageBytesToCloudinary(
    Uint8List bytes,
    String filename,
  ) async {
    const cloudName = 'dyf9r2al9';
    const uploadPreset = 'Absensi';

    final url = Uri.parse('https://api.cloudinary.com/v1_1/$cloudName/image/upload');

    // Compress image bytes before upload for better performance
    final compressedBytes = await ImageCompressionService.compressImageBytes(bytes);
    final bytesToUpload = compressedBytes ?? bytes; // Fallback to original if compression fails

    final request = http.MultipartRequest('POST', url)
      ..fields['upload_preset'] = uploadPreset
      ..files.add(
        http.MultipartFile.fromBytes(
          'file',
          bytesToUpload,
          filename: filename,
          contentType: MediaType('image', 'jpeg'),
        ),
      );

    final response = await request.send();

    if (response.statusCode == 200) {
      final respStr = await response.stream.bytesToString();
      final data = json.decode(respStr);
      return {
        'url': data['secure_url'],
        'public_id': data['public_id']
      };
    } else {
      print('Upload failed (web): ${response.statusCode}');
      return null;
    }
  }
}
