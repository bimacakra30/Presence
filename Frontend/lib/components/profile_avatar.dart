import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';

class ProfileAvatar extends StatelessWidget {
  const ProfileAvatar({super.key});

  @override
  Widget build(BuildContext context) {
    User? user = FirebaseAuth.instance.currentUser ;

    return CircleAvatar(
      radius: 26,
      backgroundColor: Colors.white,
      backgroundImage: user?.photoURL != null
          ? NetworkImage(user!.photoURL!)
          : null,
      child: user?.photoURL == null
          ? const Icon(Icons.person, size: 30, color: Color.fromARGB(255, 30, 30, 30))
          : null,
    );
  }
}