import 'package:flutter/material.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'login_page.dart';
import 'history.dart';

class SettingsPage extends StatelessWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context) {
    final primaryColor = Colors.blue.shade800;
    final backgroundColor = const Color(0xFFF5F5F5);

    return Scaffold(
      backgroundColor: backgroundColor,
      appBar: AppBar(
        backgroundColor: backgroundColor,
        elevation: 0,
        leading: const BackButton(color: Colors.black),
        title: const Text(
          'Settings',
          style: TextStyle(
            color: Colors.black,
            fontSize: 22,
            fontWeight: FontWeight.bold,
          ),
        ),
        centerTitle: false,
      ),
      body: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
        children: [
          const SizedBox(height: 10),
          _SectionTitle(icon: Icons.person, title: 'Account'),
          const SizedBox(height: 6),
          _SectionItem(
            title: 'Present History',
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (context) => const AttendanceHistoryPage()),
              );
            },
          ),
          _SectionItem(title: 'Edit Profile', onTap: () {}),
          _SectionItem(title: 'Change Password', onTap: () {}),

          const SizedBox(height: 20),
          _SectionTitle(icon: Icons.notifications, title: 'Notifications'),
          const SizedBox(height: 6),
          const _SectionSwitch(title: 'Push Notifications'),
          const _SectionSwitch(title: 'App Notifications'),

          const SizedBox(height: 20),
          _SectionTitle(icon: Icons.more_horiz, title: 'More'),
          const SizedBox(height: 6),
          _SectionItem(title: 'Language', onTap: () {}),
          _SectionItem(title: 'About', onTap: () {}),

          const SizedBox(height: 40),
          Center(
            child: ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: primaryColor,
                foregroundColor: Colors.white,
                elevation: 2,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(30),
                ),
              ),
              icon: const Icon(Icons.logout),
              label: const Text(
                'Logout',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              onPressed: () async {
                await FirebaseAuth.instance.signOut();
                if (context.mounted) {
                  Navigator.pushAndRemoveUntil(
                    context,
                    MaterialPageRoute(builder: (context) => const LoginPage()),
                        (route) => false,
                  );
                }
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final IconData icon;
  final String title;

  const _SectionTitle({required this.icon, required this.title});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: Colors.blue.shade700),
        const SizedBox(width: 8),
        Text(
          title,
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: Colors.blue.shade700,
          ),
        ),
      ],
    );
  }
}

class _SectionItem extends StatelessWidget {
  final String title;
  final VoidCallback onTap;

  const _SectionItem({required this.title, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      splashColor: Colors.blue,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 12.0),
        child: Text(
          title,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w500,
            color: Colors.black87,
          ),
        ),
      ),
    );
  }
}

class _SectionSwitch extends StatefulWidget {
  final String title;

  const _SectionSwitch({required this.title});

  @override
  State<_SectionSwitch> createState() => _SectionSwitchState();
}

class _SectionSwitchState extends State<_SectionSwitch> {
  bool _value = false;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile(
      contentPadding: EdgeInsets.zero,
      value: _value,
      title: Text(
        widget.title,
        style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
      ),
      onChanged: (val) => setState(() => _value = val),
      activeColor: Colors.blue.shade800,
    );
  }
}
