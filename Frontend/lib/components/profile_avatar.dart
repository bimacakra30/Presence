import 'package:flutter/material.dart';

class ProfileAvatar extends StatelessWidget {
  final String? profilePictureUrl;
  final String? name;

  const ProfileAvatar({
    super.key,
    this.profilePictureUrl,
    this.name,
  });

  String _getInitials(String name) {
    if (name.isEmpty) {
      return 'U'; // Default initial
    }
    final nameParts = name.trim().split(' ');
    if (nameParts.length > 1) {
      return (nameParts[0][0] + nameParts[1][0]).toUpperCase();
    }
    return nameParts[0][0].toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    // Tentukan inisial dari nama jika nama tidak null atau kosong
    final String initials = (name != null && name!.isNotEmpty) ? _getInitials(name!) : 'U';

    return CircleAvatar(
      radius: 26,
      backgroundColor: Colors.blueGrey,
      backgroundImage: (profilePictureUrl != null && profilePictureUrl!.isNotEmpty)
          ? NetworkImage(profilePictureUrl!)
          : null,
      child: (profilePictureUrl == null || profilePictureUrl!.isEmpty)
          ? Text(
              initials,
              style: const TextStyle(
                fontSize: 24,
                fontWeight: FontWeight.bold,
                color: Colors.white,
              ),
            )
          : null,
    );
  }
}