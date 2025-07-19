import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;

class CloudinaryService {
  static Future<Map<String, dynamic>?> uploadImageToCloudinary(File imageFile) async {
    const cloudName = 'dyf9r2al9';
    const uploadPreset = 'Absensi';

    final url = Uri.parse('https://api.cloudinary.com/v1_1/$cloudName/image/upload');

    final request = http.MultipartRequest('POST', url)
      ..fields['upload_preset'] = uploadPreset
      ..files.add(await http.MultipartFile.fromPath('file', imageFile.path));

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
}
